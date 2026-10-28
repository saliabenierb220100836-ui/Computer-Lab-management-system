<?php

namespace App\Http\Controllers;

use App\Models\AcademicGroup;
use App\Models\ActivitySession;
use App\Models\ClassSchedule;
use App\Models\ScheduleEnrollment;
use App\Models\Student;
use App\Models\Terminal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseRosterController extends Controller
{
    public function index(Request $request, ClassSchedule $schedule): View
    {
        $this->authorizeSchedule($request, $schedule);
        $schedule->load(['instructor', 'academicGroup', 'enrollments.student.academicGroup', 'enrollments.terminal']);

        return view('course-roster.index', [
            'schedule' => $schedule,
            'academicGroups' => AcademicGroup::query()
                ->orderBy('course')
                ->orderBy('section')
                ->get()
                ->filter(fn (AcademicGroup $academicGroup) => $academicGroup->hasValidSectionFormat())
                ->values(),
            'terminals' => Terminal::query()
                ->where('status', 'available')
                ->whereDoesntHave('activitySessions', fn ($query) => $query->whereNull('logout_time'))
                ->whereNotIn('id', $schedule->enrollments()->whereNotNull('terminal_id')->select('terminal_id'))
                ->orderBy('pc_name')
                ->get(),
        ]);
    }

    public function show(Request $request, ClassSchedule $schedule, Student $student): View
    {
        $this->authorizeSchedule($request, $schedule);
        $enrollment = $schedule->enrollments()->where('user_id', $student->id)->with('terminal')->firstOrFail();
        $attendance = ActivitySession::query()
            ->where('schedule_id', $schedule->id)
            ->where('user_id', $student->id)
            ->latest('login_time')
            ->get();

        return view('course-roster.student', compact('schedule', 'student', 'enrollment', 'attendance'));
    }

    public function certificate(Request $request, ClassSchedule $schedule, Student $student): BinaryFileResponse
    {
        $this->authorizeSchedule($request, $schedule);
        $schedule->enrollments()->where('user_id', $student->id)->firstOrFail();
        abort_unless($student->registration_certificate_path, 404);
        $path = $student->registration_certificate_path;
        abort_unless(Storage::disk('local')->exists($path), 404, 'Registration certificate file not found.');

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, ClassSchedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($request, $schedule);
        abort_if(! $schedule->academic_group_id, 422, 'This class has no course and section reference. Update the class schedule first.');
        $request->merge([
            'student_id' => is_string($request->input('student_id'))
                ? strtoupper(trim($request->input('student_id')))
                : $request->input('student_id'),
        ]);
        $data = $request->validate([
            'student_id' => ['required', 'string', 'exists:students,student_or_employee_id'],
            'terminal_id' => ['nullable', 'integer', 'exists:terminals,id'],
        ]);
        $student = Student::query()
            ->where('student_or_employee_id', $data['student_id'])
            ->whereNotNull('email_verified_at')
            ->first();

        if (! $student || ! $student->hasOfficialStudentEmail()) {
            return back()->withErrors(['student_id' => 'Student not found, or their school email is not verified.']);
        }
        if (! $student->academic_group_id || (int) $student->academic_group_id !== (int) $schedule->academic_group_id) {
            return back()->withErrors([
                'student_id' => "This student is not assigned to {$schedule->academicGroup->label} and cannot be added to this class.",
            ]);
        }

        $this->assertStationAvailableForClass($schedule, $data['terminal_id'] ?? null, $student->id);
        $enrollment = ScheduleEnrollment::query()->firstOrNew([
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
        ]);
        if (! $enrollment->exists) {
            $enrollment->source = 'instructor_assigned';
        }
        if (! empty($data['terminal_id'])) {
            $enrollment->terminal_id = $data['terminal_id'];
        }
        $enrollment->save();

        return back()->with('success', "{$student->name} has been added to the {$schedule->subject_code} course roster.");
    }

    public function updateStation(Request $request, ClassSchedule $schedule, ScheduleEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeSchedule($request, $schedule);
        abort_unless($enrollment->schedule_id === $schedule->id, 404);
        $data = $request->validate([
            'terminal_id' => ['nullable', 'integer', 'exists:terminals,id'],
        ]);
        abort_if(
            ActivitySession::query()
                ->where('schedule_id', $schedule->id)
                ->where('user_id', $enrollment->user_id)
                ->whereNull('logout_time')
                ->exists(),
            422,
            'Check out the student before changing their assigned station.',
        );

        DB::transaction(function () use ($schedule, $enrollment, $data): void {
            $lockedEnrollment = ScheduleEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $terminalId = isset($data['terminal_id']) ? (int) $data['terminal_id'] : null;
            if ($terminalId !== null && $terminalId !== (int) $lockedEnrollment->terminal_id) {
                Terminal::query()->lockForUpdate()->findOrFail($terminalId);
                $this->assertStationAvailableForClass($schedule, $terminalId, $lockedEnrollment->user_id);
            }

            $lockedEnrollment->update(['terminal_id' => $terminalId]);
        });

        return back()->with('success', 'Student station assignment updated.');
    }

    public function updateAcademicGroup(Request $request, ClassSchedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($request, $schedule);
        $data = $request->validate([
            'academic_group_id' => ['required', 'integer', 'exists:academic_groups,id', AcademicGroup::selectionRule()],
        ]);
        $academicGroup = AcademicGroup::query()->findOrFail($data['academic_group_id']);

        abort_if(
            $schedule->enrollments()
                ->whereHas('student', fn ($query) => $query
                    ->whereNull('academic_group_id')
                    ->orWhere('academic_group_id', '!=', $academicGroup->id))
                ->exists(),
            422,
            'Every enrolled student must be assigned to this course and section before the class reference can be changed.',
        );

        $schedule->update([
            'academic_group_id' => $academicGroup->id,
            'section' => $academicGroup->section,
        ]);

        return back()->with('success', 'Class course and section reference updated.');
    }

    public function import(Request $request, ClassSchedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($request, $schedule);
        abort_if(! $schedule->academic_group_id, 422, 'This class has no course and section reference. Update the class schedule first.');
        $validated = $request->validate([
            'roster' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);
        $stream = fopen($validated['roster']->getRealPath(), 'r');
        if ($stream === false) {
            return back()->withErrors(['roster' => 'Unable to read the uploaded roster.']);
        }

        try {
            $header = fgetcsv($stream, 4096, ',', '"', '\\');
            if ($header === false) {
                return back()->withErrors(['roster' => 'The roster file is empty.']);
            }
            $header = array_map(fn ($column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), $header);
            $idColumn = array_search('student_number', $header, true);
            if ($idColumn === false) {
                $idColumn = array_search('student_or_employee_id', $header, true);
            }
            if ($idColumn === false) {
                return back()->withErrors(['roster' => 'CSV must include a student_number or student_or_employee_id column.']);
            }

            $numbers = [];
            $rowCount = 0;
            while (($row = fgetcsv($stream, 4096, ',', '"', '\\')) !== false) {
                if (++$rowCount > 2000) {
                    return back()->withErrors(['roster' => 'A roster can contain no more than 2,000 rows.']);
                }
                $number = strtoupper(trim((string) ($row[$idColumn] ?? '')));
                if ($number !== '') {
                    $numbers[] = $number;
                }
            }
        } finally {
            fclose($stream);
        }

        if ($numbers === []) {
            return back()->withErrors(['roster' => 'No student numbers were found in the roster.']);
        }

        $numbers = array_values(array_unique($numbers));
        $registeredStudents = Student::withTrashed()
            ->whereIn('student_or_employee_id', $numbers)
            ->get(['id', 'student_or_employee_id', 'email_verified_at', 'deleted_at', 'email', 'academic_group_id']);
        $students = Student::query()
            ->whereNotNull('email_verified_at')
            ->whereIn('student_or_employee_id', $numbers)
            ->where('academic_group_id', $schedule->academic_group_id)
            ->get()
            ->filter(fn (Student $student) => $student->hasOfficialStudentEmail())
            ->keyBy(fn (Student $student) => strtoupper($student->student_or_employee_id));

        $imported = 0;
        $removed = 0;
        $registeredNumbers = $registeredStudents->mapWithKeys(fn (Student $student) => [
            strtoupper($student->student_or_employee_id) => $student,
        ]);
        $notRegistered = array_values(array_diff($numbers, $registeredNumbers->keys()->all()));
        $notEligible = array_values(array_intersect(
            array_diff($numbers, $students->keys()->all()),
            $registeredNumbers->keys()->all(),
        ));
        $wrongAcademicGroup = $registeredStudents
            ->filter(fn (Student $student) => in_array(strtoupper($student->student_or_employee_id), $numbers, true)
                && (int) $student->academic_group_id !== (int) $schedule->academic_group_id)
            ->map(fn (Student $student) => strtoupper($student->student_or_employee_id))
            ->values()
            ->all();
        DB::transaction(function () use ($schedule, $numbers, $students, &$imported, &$removed): void {
            $eligibleIds = $students->pluck('id')->all();
            $omittedEnrollmentIds = $schedule->enrollments()
                ->whereNotIn('user_id', $eligibleIds)
                ->pluck('user_id');
            if ($omittedEnrollmentIds->isNotEmpty()) {
                ActivitySession::query()
                    ->where('schedule_id', $schedule->id)
                    ->whereIn('user_id', $omittedEnrollmentIds)
                    ->whereNull('logout_time')
                    ->update(['logout_time' => now()]);
                $removed = $schedule->enrollments()
                    ->whereIn('user_id', $omittedEnrollmentIds)
                    ->delete();
            }

            foreach ($numbers as $number) {
                $student = $students->get($number);
                if (! $student) {
                    continue;
                }

                $enrollment = ScheduleEnrollment::query()->firstOrNew([
                    'schedule_id' => $schedule->id,
                    'user_id' => $student->id,
                ]);
                $enrollment->source = 'official_roster_import';
                $enrollment->save();
                $imported++;
            }
        });

        $response = back()->with(
            'success',
            "Roster import complete: {$imported} verified students matched to the CSV; {$removed} existing student(s) not in the CSV removed from this class roster.",
        );
        if ($notRegistered !== []) {
            $response->with('roster_not_registered', $notRegistered);
        }
        $notEligible = array_values(array_diff($notEligible, $wrongAcademicGroup));
        if ($notEligible !== []) {
            $response->with('roster_not_eligible', $notEligible);
        }
        if ($wrongAcademicGroup !== []) {
            $response->with('roster_wrong_academic_group', $wrongAcademicGroup);
        }

        return $response;
    }

    public function destroy(Request $request, ClassSchedule $schedule, ScheduleEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeSchedule($request, $schedule);
        abort_unless($enrollment->schedule_id === $schedule->id, 404);
        abort_if(
            $enrollment->student->activitySessions()
                ->where('schedule_id', $schedule->id)
                ->whereNull('logout_time')
                ->exists(),
            422,
            'Check out the student before removing them from this course roster.',
        );
        $enrollment->delete();

        return back()->with('success', 'Student removed from the course roster.');
    }

    private function authorizeSchedule(Request $request, ClassSchedule $schedule): void
    {
        abort_unless($request->user()->role === 'admin' || $schedule->instructor_id === $request->user()->id, 403);
    }

    private function assertStationAvailableForClass(ClassSchedule $schedule, ?int $terminalId, int $studentId): void
    {
        if (! $terminalId) {
            return;
        }

        $terminal = Terminal::query()->findOrFail($terminalId);
        abort_if($terminal->status !== 'available', 422, 'The selected station is under maintenance.');
        $stationAssigned = $schedule->enrollments()
            ->where('terminal_id', $terminalId)
            ->where('user_id', '!=', $studentId)
            ->exists();
        abort_if($stationAssigned, 422, 'That station is already assigned in this class.');

        $stationReserved = ScheduleEnrollment::query()
            ->where('terminal_id', $terminalId)
            ->where('user_id', '!=', $studentId)
            ->whereHas('schedule', fn ($query) => $query
                ->whereNull('dismissed_at')
                ->where('starts_at', '<', $schedule->ends_at)
                ->where('ends_at', '>', $schedule->starts_at))
            ->exists();
        abort_if($stationReserved, 422, 'That station is assigned to another class during this time.');

        abort_if(
            ActivitySession::query()->where('terminal_id', $terminalId)->whereNull('logout_time')->exists(),
            422,
            'That station is currently occupied.',
        );
    }
}
