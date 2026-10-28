<?php

namespace App\Http\Controllers;

use App\Models\AcademicGroup;
use App\Models\AcademicGroupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicGroupRequestController extends Controller
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
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $alreadyPending = AcademicGroupRequest::query()
                        ->where('requested_by', $request->user()->id)
                        ->where('course', $request->input('course'))
                        ->where('section', $value)
                        ->where('status', 'pending')
                        ->exists();
                    if ($alreadyPending) {
                        $fail('You already have a pending request for this course and section.');
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], ['section.regex' => 'Use the format COURSE #-#, matching the course code (for example, BSIT 3-1).']);

        AcademicGroupRequest::query()->create([
            ...$data,
            'requested_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Your course and section request was sent to the administrator.');
    }

    public function approve(Request $request, AcademicGroupRequest $academicGroupRequest): RedirectResponse
    {
        DB::transaction(function () use ($request, $academicGroupRequest): void {
            $lockedRequest = AcademicGroupRequest::query()->lockForUpdate()->findOrFail($academicGroupRequest->id);
            abort_if($lockedRequest->status !== 'pending', 422, 'This section request has already been reviewed.');
            abort_unless(
                preg_match(AcademicGroup::sectionPatternForCourse($lockedRequest->course), $lockedRequest->section) === 1,
                422,
                'This request does not match the required COURSE #-# section format. Reject it and submit a corrected request.',
            );
            AcademicGroup::query()->firstOrCreate([
                'course' => $lockedRequest->course,
                'section' => $lockedRequest->section,
            ]);
            $lockedRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('success', 'The request was approved and the course and section were added to the catalog.');
    }

    public function reject(Request $request, AcademicGroupRequest $academicGroupRequest): RedirectResponse
    {
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $academicGroupRequest, $data): void {
            $lockedRequest = AcademicGroupRequest::query()->lockForUpdate()->findOrFail($academicGroupRequest->id);
            abort_if($lockedRequest->status !== 'pending', 422, 'This section request has already been reviewed.');
            $lockedRequest->update([
                ...$data,
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('success', 'The course and section request was rejected.');
    }
}
