<?php

namespace App\Http\Controllers;

use App\Models\AcademicGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAcademicGroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'course' => is_string($request->input('course')) ? strtoupper(trim($request->input('course'))) : $request->input('course'),
            'section' => is_string($request->input('section')) ? strtoupper(trim($request->input('section'))) : $request->input('section'),
        ]);
        $data = $request->validate([
            'course' => ['required', 'string', 'max:80'],
            'section' => [
                'required',
                'string',
                'max:60',
                Rule::regex(AcademicGroup::sectionPatternForCourse((string) $request->input('course'))),
                Rule::unique('academic_groups', 'section')->where(
                    fn ($query) => $query->where('course', $request->input('course')),
                ),
            ],
        ], ['section.regex' => 'Use the format COURSE #-#, matching the course code (for example, BSIT 3-1).']);

        AcademicGroup::query()->create($data);

        return back()->with('success', 'Course and section added to the catalog.');
    }

    public function update(Request $request, AcademicGroup $academicGroup): RedirectResponse
    {
        $request->merge([
            'course' => is_string($request->input('course')) ? strtoupper(trim($request->input('course'))) : $request->input('course'),
            'section' => is_string($request->input('section')) ? strtoupper(trim($request->input('section'))) : $request->input('section'),
        ]);
        $data = $request->validate([
            'course' => ['required', 'string', 'max:80'],
            'section' => [
                'required',
                'string',
                'max:60',
                Rule::regex(AcademicGroup::sectionPatternForCourse((string) $request->input('course'))),
                Rule::unique('academic_groups', 'section')
                    ->where(fn ($query) => $query->where('course', $request->input('course')))
                    ->ignore($academicGroup->id),
            ],
        ], ['section.regex' => 'Use the format COURSE #-#, matching the course code (for example, BSIT 3-1).']);

        DB::transaction(function () use ($academicGroup, $data): void {
            $academicGroup->update($data);
            $academicGroup->students()->update(['section' => $data['section']]);
            $academicGroup->schedules()->update(['section' => $data['section']]);
        });

        return back()->with('success', 'Course and section updated.');
    }

    public function destroy(AcademicGroup $academicGroup): RedirectResponse
    {
        if ($academicGroup->students()->exists() || $academicGroup->schedules()->exists()) {
            return back()->withErrors([
                'academic_group' => 'This course and section is in use. Reassign its students and schedules before deleting it.',
            ]);
        }

        $academicGroup->delete();

        return back()->with('success', 'Course and section removed from the catalog.');
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);
        $stream = fopen($data['file']->getRealPath(), 'r');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV file could not be read.']);
        }

        try {
            $header = fgetcsv($stream, 4096, ',', '"', '\\');
            if ($header !== false && isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
                $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);
            }
            if ($header !== ['course', 'section']) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV must start with these two columns, in order: course,section.',
                ]);
            }

            $groups = [];
            $line = 1;
            while (($row = fgetcsv($stream, 4096, ',', '"', '\\')) !== false) {
                $line++;
                $row = array_map(fn ($value) => trim((string) $value), $row);
                if (count($row) === 1 && $row[0] === '') {
                    continue;
                }
                if (count($row) !== 2) {
                    throw ValidationException::withMessages([
                        'file' => "CSV row {$line} must contain exactly a course and a section.",
                    ]);
                }

                $row[0] = strtoupper($row[0]);
                $row[1] = strtoupper($row[1]);
                $validator = Validator::make(
                    ['course' => $row[0], 'section' => $row[1]],
                    [
                        'course' => ['required', 'string', 'max:80'],
                        'section' => [
                            'required',
                            'string',
                            'max:60',
                            Rule::regex(AcademicGroup::sectionPatternForCourse($row[0])),
                        ],
                    ],
                );
                if ($validator->fails()) {
                    throw ValidationException::withMessages([
                        'file' => "CSV row {$line}: ".$validator->errors()->first(),
                    ]);
                }

                $groups[$row[0]."\0".$row[1]] = ['course' => $row[0], 'section' => $row[1]];
                if (count($groups) > 1000) {
                    throw ValidationException::withMessages([
                        'file' => 'The CSV cannot contain more than 1,000 distinct course and section rows.',
                    ]);
                }
            }
        } finally {
            fclose($stream);
        }

        if ($groups === []) {
            throw ValidationException::withMessages(['file' => 'The CSV does not contain any course and section rows.']);
        }

        $created = 0;
        DB::transaction(function () use ($groups, &$created): void {
            foreach ($groups as $group) {
                $academicGroup = AcademicGroup::query()->firstOrCreate($group);
                if ($academicGroup->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        $skipped = count($groups) - $created;

        return back()->with('success', "Import complete: {$created} added and {$skipped} already existing or duplicate row(s) skipped.");
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(static function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['course', 'section']);
            fclose($stream);
        }, 'academic-groups-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
