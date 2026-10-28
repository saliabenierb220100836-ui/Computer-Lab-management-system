<?php

namespace App\Http\Controllers;

use App\Models\AcademicGroup;
use App\Models\AcademicGroupRequest;
use App\Models\ActivitySession;
use App\Models\ClassSchedule;
use App\Models\Faculty;
use App\Models\MaintenanceTicket;
use App\Models\ScheduleEnrollment;
use App\Models\Student;
use App\Models\Terminal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LabController extends Controller
{
    public function dashboard(Request $request, string $page = 'overview')
    {
        $user = $request->user();
        $allowedPages = match ($user->role) {
            'admin' => ['overview', 'floor-map', 'classes', 'session-log', 'active-users', 'maintenance', 'accounts', 'academic-groups'],
            'instructor', 'student' => ['overview', 'floor-map', 'classes', 'session-log', 'fault-reports'],
            default => ['overview'],
        };
        abort_unless(in_array($page, $allowedPages, true), 403);

        $pageTitles = [
            'overview' => 'Overview',
            'floor-map' => 'Floor map',
            'classes' => $user->role === 'student' ? 'My schedule' : 'Classes & schedules',
            'session-log' => 'Session log',
            'active-users' => 'Currently logged in',
            'fault-reports' => 'Fault reports',
            'maintenance' => 'Maintenance queue',
            'accounts' => 'User accounts',
            'academic-groups' => 'Courses & sections',
        ];
        $terminals = Terminal::query()
            ->with(['activeSession.user', 'activeSession.schedule', 'activeReservation.schedule'])
            ->orderBy('grid_y')
            ->orderBy('grid_x')
            ->get();

        $schedules = $this->visibleSchedules($user)
            ->whereNull('dismissed_at')
            ->with([
                'instructor',
                'academicGroup',
                'enrollments.student',
                'enrollments.terminal',
                'activitySessions',
                'activitySessions.user',
                'activitySessions.terminal',
            ])
            ->orderBy('starts_at')
            ->limit(12)
            ->get();
        $dismissedSchedules = $page === 'session-log' && $user->role !== 'student'
            ? $this->visibleSchedules($user)
                ->whereNotNull('dismissed_at')
                ->with(['instructor', 'enrollments.student', 'activitySessions.user', 'activitySessions.terminal'])
                ->orderByDesc('dismissed_at')
                ->limit(50)
                ->get()
            : collect();
        $attendanceSummaries = $dismissedSchedules
            ->mapWithKeys(fn (ClassSchedule $schedule) => [
                $schedule->id => $this->attendanceSummary($schedule),
            ]);
        $academicGroups = in_array($page, ['classes', 'accounts', 'academic-groups'], true)
            ? AcademicGroup::query()->withCount(['students', 'schedules'])->orderBy('course')->orderBy('section')->get()
            : collect();
        $selectableAcademicGroups = $academicGroups->filter(
            fn (AcademicGroup $academicGroup) => $academicGroup->hasValidSectionFormat(),
        )->values();

        $ticketsQuery = MaintenanceTicket::query()
            ->with(['terminal', 'reporter'])
            ->latest();
        if ($user->role !== 'admin') {
            $ticketsQuery->where('reported_by', $user->id);
        }

        $sessionsQuery = $this->visibleSessions($user)
            ->whereNull('schedule_id')
            ->with(['user', 'terminal', 'schedule']);
        $ownSession = $user->activitySessions()->whereNull('logout_time')->with('terminal')->first();
        $loggedInUsers = collect();
        $checkedInSessions = collect();
        $sessionLogDates = collect();
        $activeUserCounts = collect(['student' => 0, 'instructor' => 0]);
        $activeUserRole = in_array($request->query('role'), ['student', 'instructor'], true)
            ? $request->query('role')
            : 'student';
        $accountRole = in_array($request->query('role'), ['student', 'instructor'], true)
            ? $request->query('role')
            : 'student';
        $managedAccountCounts = collect(['student' => 0, 'instructor' => 0]);
        if ($user->role === 'admin' && in_array($page, ['session-log', 'active-users'], true)) {
            $loggedInUsers = DB::table('account_directory')
                ->join(config('session.table').' as web_sessions', 'web_sessions.user_id', '=', 'account_directory.id')
                ->whereNotNull('web_sessions.user_id')
                ->where('web_sessions.last_activity', '>=', now()->subMinutes(config('session.lifetime'))->timestamp)
                ->whereNull('account_directory.deleted_at')
                ->whereIn('account_directory.role', ['student', 'instructor'])
                ->select('account_directory.id', 'account_directory.name', 'account_directory.student_or_employee_id', 'account_directory.role')
                ->selectRaw('MAX(web_sessions.last_activity) as last_activity')
                ->groupBy('account_directory.id', 'account_directory.name', 'account_directory.student_or_employee_id', 'account_directory.role')
                ->orderByDesc('last_activity')
                ->get();
            $activeUserCounts = collect([
                'student' => $loggedInUsers->where('role', 'student')->count(),
                'instructor' => $loggedInUsers->where('role', 'instructor')->count(),
            ]);
            if ($page === 'active-users') {
                $loggedInUsers = $loggedInUsers
                    ->where('role', $activeUserRole)
                    ->values();
            }
            $checkedInSessions = ActivitySession::query()
                ->whereNull('logout_time')
                ->whereIn('user_id', $loggedInUsers->pluck('id'))
                ->with('terminal')
                ->get()
                ->keyBy('user_id');
            if ($page === 'session-log') {
                $sessionLogDates = ActivitySession::query()
                    ->selectRaw('DATE(login_time) as session_date, COUNT(*) as session_count')
                    ->groupBy('session_date')
                    ->orderByDesc('session_date')
                    ->limit(100)
                    ->get();
            }
        }
        if ($user->role === 'admin' && $page === 'accounts') {
            $managedAccountCounts = collect([
                'student' => Student::query()->count(),
                'instructor' => Faculty::query()->count(),
            ]);
        }

        return view('dashboard', [
            'page' => $page,
            'pageTitle' => $pageTitles[$page],
            'terminals' => $terminals,
            'schedules' => $schedules,
            'dismissedSchedules' => $dismissedSchedules,
            'attendanceSummaries' => $attendanceSummaries,
            'tickets' => $ticketsQuery->limit(8)->get(),
            'academicGroups' => $academicGroups,
            'selectableAcademicGroups' => $selectableAcademicGroups,
            'academicGroupRequests' => $user->role === 'admin' && $page === 'academic-groups'
                ? AcademicGroupRequest::query()->with('requester')->where('status', 'pending')->latest()->limit(100)->get()
                : ($user->role === 'instructor' && $page === 'classes'
                    ? AcademicGroupRequest::query()->where('requested_by', $user->id)->latest()->limit(10)->get()
                    : collect()),
            'recentSessions' => $sessionsQuery->latest('login_time')->limit(8)->get(),
            'loggedInUsers' => $loggedInUsers,
            'activeUserCounts' => $activeUserCounts,
            'activeUserRole' => $activeUserRole,
            'checkedInSessions' => $checkedInSessions,
            'sessionLogDates' => $sessionLogDates,
            'managedUsers' => $user->role === 'admin' && $page === 'accounts'
                ? ($accountRole === 'instructor' ? Faculty::query() : Student::query())->latest()->paginate(25, ['*'], 'users_page')->withQueryString()
                : collect(),
            'accountRole' => $accountRole,
            'managedAccountCounts' => $managedAccountCounts,
            'ownSession' => $ownSession,
            'counts' => [
                'available' => $terminals->filter(fn (Terminal $terminal) => $this->terminalStatus($terminal) === 'available')->count(),
                'occupied' => $terminals->filter(fn (Terminal $terminal) => $this->terminalStatus($terminal) === 'occupied')->count(),
                'reserved' => $terminals->filter(fn (Terminal $terminal) => $this->terminalStatus($terminal) === 'reserved')->count(),
                'maintenance' => $terminals->filter(fn (Terminal $terminal) => $this->terminalStatus($terminal) === 'maintenance')->count(),
            ],
            'statusFor' => fn (Terminal $terminal) => $this->terminalStatus($terminal),
        ]);
    }

    public function updateTerminal(Request $request, Terminal $terminal): RedirectResponse
    {
        $data = $request->validate([
            'pc_name' => ['required', 'string', 'max:30', Rule::unique('terminals', 'pc_name')->ignore($terminal->id)],
            'grid_x' => [
                'required',
                'integer',
                'between:1,8',
                Rule::unique('terminals', 'grid_x')
                    ->where(fn (QueryBuilder $query) => $query->where('grid_y', $request->input('grid_y')))
                    ->ignore($terminal->id),
            ],
            'grid_y' => ['required', 'integer', 'between:1,5'],
            'status' => ['required', Rule::in(['available', 'maintenance'])],
            'specs_description' => ['nullable', 'string', 'max:500'],
        ]);
        $terminal->update($data);

        return back()->with('success', "{$terminal->pc_name} has been updated.");
    }

    public function forceReleaseTerminal(Request $request, Terminal $terminal): RedirectResponse
    {
        DB::transaction(function () use ($terminal): void {
            $lockedTerminal = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
            $activeSessions = ActivitySession::query()
                ->where('terminal_id', $lockedTerminal->id)
                ->whereNull('logout_time');
            abort_if(! $activeSessions->exists(), 422, 'This station has no active session to release.');
            abort_if($lockedTerminal->status === 'maintenance', 422, 'A station under maintenance cannot be marked available.');
            $activeSessions->update(['logout_time' => now()]);
            $lockedTerminal->update(['status' => 'available']);
        });

        return back()->with('success', "{$terminal->pc_name} has been forcefully checked out and marked available.");
    }

    public function checkIn(Request $request, Terminal $terminal): RedirectResponse
    {
        $data = $request->validate(['purpose' => ['nullable', 'string', 'max:160']]);
        $user = $request->user();

        if ($user->role !== 'student' || ! $user->hasOfficialStudentEmail()) {
            abort(403);
        }

        $classEnrollment = ScheduleEnrollment::query()
            ->where('user_id', $user->id)
            ->whereHas('schedule', fn (Builder $query) => $query
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->whereNull('dismissed_at'))
            ->orderByRaw('CASE WHEN terminal_id = ? THEN 0 WHEN terminal_id IS NULL THEN 1 ELSE 2 END', [$terminal->id])
            ->with('schedule')
            ->first();

        abort_if(
            $classEnrollment
                && (! $user->academic_group_id
                    || (int) $classEnrollment->schedule->academic_group_id !== (int) $user->academic_group_id),
            422,
            'Your registered course and section do not match this class. Contact your instructor to update your academic group.',
        );

        DB::transaction(function () use ($terminal, $user, $data, $classEnrollment): void {
            Student::query()->lockForUpdate()->findOrFail($user->id);
            $lockedTerminal = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
            $enrollment = $classEnrollment
                ? ScheduleEnrollment::query()->lockForUpdate()->findOrFail($classEnrollment->id)
                : null;
            abort_if($lockedTerminal->status !== 'available', 422, 'This station is not available.');
            abort_if(
                ActivitySession::query()->where('terminal_id', $lockedTerminal->id)->whereNull('logout_time')->exists(),
                422,
                'This station is already occupied.',
            );
            abort_if(
                ActivitySession::query()->where('user_id', $user->id)->whereNull('logout_time')->exists(),
                422,
                'You already have an active session. Check out before choosing another station.',
            );

            $reservation = ScheduleEnrollment::query()
                ->where('terminal_id', $lockedTerminal->id)
                ->whereHas('schedule', fn (Builder $query) => $query
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>', now())
                    ->whereNull('dismissed_at'))
                ->first();
            if ($reservation && (
                ! $enrollment
                || (int) $reservation->user_id !== (int) $user->id
                || (int) $enrollment->schedule_id !== (int) $reservation->schedule_id
            )) {
                abort(422, 'This station is reserved for another student.');
            }
            if ($enrollment?->terminal_id && $enrollment->terminal_id !== $lockedTerminal->id) {
                abort(422, 'Your class has assigned you to a different station.');
            }

            ActivitySession::query()->create([
                'user_id' => $user->id,
                'terminal_id' => $lockedTerminal->id,
                'schedule_id' => $enrollment?->schedule_id,
                'purpose' => $data['purpose'] ?? $classEnrollment?->schedule->subject_code ?? 'Open lab',
                'login_time' => now(),
            ]);
            if ($enrollment && $enrollment->terminal_id === null) {
                $enrollment->update(['terminal_id' => $lockedTerminal->id]);
            }
        });

        return back()->with('success', "Checked in to {$terminal->pc_name}.");
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $session = ActivitySession::query()->whereNull('logout_time')->findOrFail($request->integer('session'));
        $user = $request->user();
        abort_unless($user->role === 'admin' || $session->user_id === $user->id, 403);
        $session->update(['logout_time' => now()]);

        return back()->with('success', "Checked out of {$session->terminal->pc_name}.");
    }

    public function createSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_code' => ['required', 'string', 'max:30'],
            'academic_group_id' => ['required', 'integer', Rule::exists('academic_groups', 'id'), AcademicGroup::selectionRule()],
            'room_number' => ['required', 'string', 'max:40'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);
        $hasConflict = ClassSchedule::query()
            ->whereNull('dismissed_at')
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->exists();
        if ($hasConflict) {
            return back()->withErrors(['starts_at' => 'The computer lab already has a class scheduled during this time. Choose a different time.'])->withInput();
        }

        DB::transaction(function () use ($data, $request): void {
            $academicGroup = AcademicGroup::query()->findOrFail($data['academic_group_id']);
            ClassSchedule::query()->create([
                ...$data,
                'section' => $academicGroup->section,
                'instructor_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Class schedule created.');
    }

    public function cancelSchedule(Request $request, ClassSchedule $schedule): RedirectResponse
    {
        abort_unless(
            $request->user()->role === 'admin' || $schedule->instructor_id === $request->user()->id,
            403,
        );
        abort_if($schedule->dismissed_at || ! $schedule->starts_at->isFuture(), 422, 'Only upcoming classes can be cancelled.');
        abort_if(
            $schedule->activitySessions()->whereNotNull('logout_time')->exists(),
            422,
            'This class has completed attendance records and cannot be cancelled.',
        );

        DB::transaction(function () use ($schedule): void {
            $schedule->activitySessions()
                ->whereNull('logout_time')
                ->update(['logout_time' => now()]);
            $schedule->enrollments()->delete();
            $schedule->delete();
        });

        return redirect()->route('workspace.page', 'classes')->with('success', 'Class schedule cancelled.');
    }

    public function startSchedule(Request $request, ClassSchedule $schedule): RedirectResponse
    {
        abort_unless(
            $request->user()->role === 'admin' || $schedule->instructor_id === $request->user()->id,
            403,
        );
        abort_if($schedule->dismissed_at || ! $schedule->starts_at->isFuture(), 422, 'This class has already started or is no longer scheduled.');
        abort_if(! $schedule->ends_at->isFuture(), 422, 'This class has already ended.');

        $hasConflict = ClassSchedule::query()
            ->where('id', '!=', $schedule->id)
            ->whereNull('dismissed_at')
            ->where('starts_at', '<', $schedule->ends_at)
            ->where('ends_at', '>', now())
            ->exists();
        if ($hasConflict) {
            return back()->withErrors(['schedule' => 'The lab is being used by another class and cannot start yet.']);
        }

        $schedule->update(['starts_at' => now()]);

        return back()->with('success', "{$schedule->subject_code} has started. Students can check in when ready.");
    }

    public function dismissSchedule(Request $request, ClassSchedule $schedule): RedirectResponse
    {
        abort_unless(
            $request->user()->role === 'admin' || $schedule->instructor_id === $request->user()->id,
            403,
        );
        abort_if($schedule->dismissed_at, 422, 'This class has already been dismissed.');
        abort_if($schedule->starts_at->isFuture(), 422, 'Start the class before dismissing it.');

        DB::transaction(function () use ($schedule): void {
            $schedule->activitySessions()
                ->whereNull('logout_time')
                ->update(['logout_time' => now()]);
            $schedule->update(['dismissed_at' => now()]);
        });

        return redirect()
            ->route('workspace.page', 'session-log')
            ->with('success', "{$schedule->subject_code} has been dismissed. The class summary is available in the session log.");
    }

    public function createTicket(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'terminal_id' => ['required', 'integer', 'exists:terminals,id'],
            'issue_type' => ['required', Rule::in(['Monitor', 'Keyboard', 'Mouse', 'Network', 'Power', 'Other'])],
            'description' => ['required', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $terminal = Terminal::query()->lockForUpdate()->findOrFail($data['terminal_id']);
            $terminal->update(['status' => 'maintenance']);
            MaintenanceTicket::query()->create([
                ...$data,
                'reported_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Fault report submitted. The station is now flagged for maintenance.');
    }

    public function updateTicket(Request $request, MaintenanceTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'resolved'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);
        DB::transaction(function () use ($ticket, $data): void {
            $ticket->update($data);
            if ($data['status'] === 'resolved' && ! $ticket->terminal->maintenanceTickets()->where('status', '!=', 'resolved')->exists()) {
                $ticket->terminal->update(['status' => 'available']);
            }
        });

        return back()->with('success', 'Maintenance ticket updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $sessions = $this->visibleSessions($user)
            ->with(['user', 'terminal', 'schedule'])
            ->latest('login_time')
            ->get();

        return response()->streamDownload(function () use ($sessions): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Student ID', 'Name', 'PC Number', 'Login Time', 'Logout Time', 'Subject / Purpose']);
            foreach ($sessions as $session) {
                $values = [
                    $session->user->student_or_employee_id,
                    $session->user->name,
                    $session->terminal->pc_name,
                    $session->login_time->toDateTimeString(),
                    $session->logout_time?->toDateTimeString() ?? '',
                    $session->schedule?->subject_code ?? $session->purpose,
                ];
                fputcsv($output, array_map(fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value, $values));
            }
            fclose($output);
        }, 'lab-session-logs.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function showAdminDailySessions(Request $request, string $date)
    {
        abort_unless($this->isValidSessionLogDate($date), 404);
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        $sessions = ActivitySession::query()
            ->whereBetween('login_time', [$day->startOfDay(), $day->endOfDay()])
            ->with(['user', 'terminal', 'schedule'])
            ->orderBy('login_time')
            ->get();

        return view('admin.session-logs.show', [
            'date' => $day,
            'sessions' => $sessions,
        ]);
    }

    public function exportAdminDailySessions(Request $request, string $date): StreamedResponse
    {
        abort_unless($this->isValidSessionLogDate($date), 404);
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        $sessions = ActivitySession::query()
            ->whereBetween('login_time', [$day->startOfDay(), $day->endOfDay()])
            ->with(['user', 'terminal', 'schedule'])
            ->orderBy('login_time')
            ->get();

        return response()->streamDownload(function () use ($sessions): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Student / Employee ID', 'Name', 'Role', 'PC Number', 'Login Time', 'Logout Time', 'Subject / Purpose']);
            foreach ($sessions as $session) {
                $values = [
                    $session->login_time->toDateString(),
                    $session->user->student_or_employee_id,
                    $session->user->name,
                    ucfirst($session->user->role),
                    $session->terminal->pc_name,
                    $session->login_time->toDateTimeString(),
                    $session->logout_time?->toDateTimeString() ?? '',
                    $session->schedule?->subject_code ?? $session->purpose,
                ];
                fputcsv($output, array_map(fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value, $values));
            }
            fclose($output);
        }, "session-logs-{$date}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportScheduleAttendance(Request $request, ClassSchedule $schedule): StreamedResponse
    {
        abort_unless(
            $request->user()->role === 'admin' || $schedule->instructor_id === $request->user()->id,
            403,
        );
        abort_unless($schedule->dismissed_at, 404);

        $schedule->load(['instructor', 'enrollments.student', 'enrollments.terminal', 'activitySessions.user', 'activitySessions.terminal']);
        $sessionsByStudent = $schedule->activitySessions
            ->sortBy('login_time')
            ->groupBy('user_id');
        $filenameSubject = preg_replace('/[^A-Za-z0-9_-]+/', '-', $schedule->subject_code);
        $filename = "{$filenameSubject}-{$schedule->section}-attendance.csv";

        return response()->streamDownload(function () use ($schedule, $sessionsByStudent): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Subject',
                'Class Section',
                'Class Date',
                'Instructor',
                'Class Duration',
                'Student ID',
                'Student Name',
                'Student Section',
                'Attendance Status',
                'PC Number',
                'Login Time',
                'Logout Time',
            ]);

            foreach ($schedule->enrollments as $enrollment) {
                $sessions = $sessionsByStudent->get($enrollment->user_id, collect());
                if ($sessions->isEmpty()) {
                    $rows = [null];
                } else {
                    $rows = $sessions;
                }

                foreach ($rows as $session) {
                    $isLate = $session && $session->login_time->gt($schedule->starts_at->copy()->addMinutes(15));
                    $values = [
                        $schedule->subject_code,
                        $schedule->section,
                        $schedule->starts_at->toDateString(),
                        $schedule->instructor->name,
                        $schedule->starts_at->diffForHumans($schedule->dismissed_at, true),
                        $enrollment->student->student_or_employee_id,
                        $enrollment->student->name,
                        $enrollment->student->section ?? '',
                        $session ? ($isLate ? 'Late' : 'Attended') : 'Absent',
                        $session?->terminal->pc_name ?? $enrollment->terminal?->pc_name ?? '',
                        $session?->login_time?->toDateTimeString() ?? '',
                        $session?->logout_time?->toDateTimeString() ?? '',
                    ];
                    fputcsv($output, array_map(
                        fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value,
                        $values,
                    ));
                }
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function showScheduleAttendance(Request $request, ClassSchedule $schedule)
    {
        abort_unless(
            $request->user()->role === 'admin' || $schedule->instructor_id === $request->user()->id,
            403,
        );
        abort_unless($schedule->dismissed_at, 404);

        $schedule->load([
            'instructor',
            'enrollments.student',
            'activitySessions.user',
            'activitySessions.terminal',
        ]);

        return view('completed-class.show', [
            'schedule' => $schedule,
            'attendanceSummary' => $this->attendanceSummary($schedule),
        ]);
    }

    private function visibleSchedules(User $user): Builder
    {
        return ClassSchedule::query()->where(fn (Builder $query) => $query
            ->where('ends_at', '>=', now())
            ->orWhereNotNull('dismissed_at'))->when(
                $user->role === 'instructor',
                fn (Builder $query) => $query->where('instructor_id', $user->id),
            )->when(
                $user->role === 'student',
                fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->where('user_id', $user->id)),
            );
    }

    private function attendanceSummary(ClassSchedule $schedule): array
    {
        $attendanceByStudent = $schedule->activitySessions
            ->groupBy('user_id')
            ->map(fn ($sessions) => $sessions->sortBy('login_time')->first());
        $lateCutoff = $schedule->starts_at->copy()->addMinutes(15);
        $attendees = $schedule->enrollments
            ->filter(fn (ScheduleEnrollment $enrollment) => $attendanceByStudent->has($enrollment->user_id))
            ->map(fn (ScheduleEnrollment $enrollment) => [
                'name' => $enrollment->student->name,
                'student_number' => $enrollment->student->student_or_employee_id,
                'late' => $attendanceByStudent[$enrollment->user_id]->login_time->gt($lateCutoff),
            ])
            ->values();
        $attendedStudentIds = $attendees->pluck('student_number')->all();
        $absentees = $schedule->enrollments
            ->reject(fn (ScheduleEnrollment $enrollment) => $attendanceByStudent->has($enrollment->user_id))
            ->map(fn (ScheduleEnrollment $enrollment) => [
                'name' => $enrollment->student->name,
                'student_number' => $enrollment->student->student_or_employee_id,
            ])
            ->values();
        $durationMinutes = max(0, (int) $schedule->starts_at->diffInMinutes($schedule->dismissed_at));

        return [
            'duration' => intdiv($durationMinutes, 60).' hr '.($durationMinutes % 60).' min',
            'attendees' => $attendees,
            'absentees' => $absentees,
            'late' => $attendees->where('late', true)->values(),
        ];
    }

    private function visibleSessions(User $user): Builder
    {
        return ActivitySession::query()->when(
            $user->role === 'student',
            fn (Builder $query) => $query->where('user_id', $user->id),
        )->when(
            $user->role === 'instructor',
            fn (Builder $query) => $query->whereHas('schedule', fn (Builder $schedules) => $schedules->where('instructor_id', $user->id)),
        );
    }

    private function isValidSessionLogDate(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function terminalStatus(Terminal $terminal): string
    {
        if ($terminal->status === 'maintenance') {
            return 'maintenance';
        }
        if ($terminal->activeSession) {
            return 'occupied';
        }
        if ($terminal->activeReservation) {
            return 'reserved';
        }

        return 'available';
    }
}
