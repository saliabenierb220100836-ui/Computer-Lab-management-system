@extends('layouts.app')

@section('title', 'Completed class attendance')

@section('body')
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span><span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="side-label">WORKSPACE</div>
        <nav class="side-nav" aria-label="Main navigation">
            <a class="nav-link" href="{{ route('dashboard') }}">Overview</a>
            <a class="nav-link" href="{{ route('workspace.page', 'floor-map') }}">Floor map</a>
            <a class="nav-link" href="{{ route('workspace.page', 'classes') }}">Classes & schedules</a>
            <a class="nav-link active" href="{{ route('workspace.page', 'session-log') }}">Session log</a>
            @if (auth()->user()->role === 'admin')
                <a class="nav-link" href="{{ route('workspace.page', 'maintenance') }}">Maintenance queue</a>
                <a class="nav-link" href="{{ route('workspace.page', 'accounts') }}">User accounts</a>
            @endif
        </nav>
        <div class="sidebar-spacer"></div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb"><a href="{{ route('workspace.page', 'session-log') }}">Session log</a> <span>/</span> <strong>Completed class</strong></div>
            <div class="topbar-right">
                <div class="user-menu"><span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'admin' ? 'Lab administrator' : 'Instructor' }}</small></span></div>
                <button class="logout-button" type="button" aria-haspopup="dialog" aria-controls="logout-dialog" data-open-logout-dialog>Log out</button>
            </div>
        </header>
        <dialog class="logout-dialog" id="logout-dialog" aria-labelledby="logout-dialog-title">
            <h2 id="logout-dialog-title">Sign out of PUP - SRComLab?</h2>
            <p>You’ll need to sign in again to access your lab workspace.</p>
            <div class="logout-dialog-actions">
                <button class="button button-secondary" type="button" data-cancel-logout>Stay signed in</button>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-primary" type="submit">Log out</button></form>
            </div>
        </dialog>
        <div class="page-content">
            <section class="page-heading roster-heading">
                <div>
                    <span class="eyebrow">{{ $schedule->subject_code }} · {{ $schedule->section }}</span>
                    <h1>Completed class attendance</h1>
                    <p>{{ $schedule->starts_at->format('M j, Y · H:i') }} - {{ $schedule->dismissed_at->format('H:i') }} · {{ $schedule->instructor->name }}</p>
                </div>
                <div class="completed-class-actions">
                    <a class="button button-secondary" href="{{ route('workspace.page', 'session-log') }}">Back to session log</a>
                    <a class="button button-primary" href="{{ route('schedules.attendance.export', $schedule) }}">Export class CSV</a>
                </div>
            </section>
            <section class="panel content-panel">
                <div class="panel-title-row"><div><span class="eyebrow">CLASS SUMMARY</span><h2>Attendance overview</h2></div><span class="count-pill">Duration: {{ $attendanceSummary['duration'] }}</span></div>
                <div class="attendance-summary-groups">
                    <div>
                        <strong>Attendees ({{ $attendanceSummary['attendees']->count() }})</strong>
                        @forelse ($attendanceSummary['attendees'] as $attendee)
                            <span>{{ $attendee['name'] }}{{ $attendee['late'] ? ' · Late' : '' }}</span>
                        @empty <span>None</span> @endforelse
                    </div>
                    <div>
                        <strong>Late ({{ $attendanceSummary['late']->count() }})</strong>
                        @forelse ($attendanceSummary['late'] as $lateStudent)
                            <span>{{ $lateStudent['name'] }}</span>
                        @empty <span>None</span> @endforelse
                    </div>
                    <div>
                        <strong>Absent ({{ $attendanceSummary['absentees']->count() }})</strong>
                        @forelse ($attendanceSummary['absentees'] as $absentStudent)
                            <span>{{ $absentStudent['name'] }}</span>
                        @empty <span>None</span> @endforelse
                    </div>
                </div>
            </section>
            <section class="panel content-panel completed-class-checkins">
                <div class="panel-title-row"><div><span class="eyebrow">ATTENDANCE RECORDS</span><h2>Student check-ins</h2></div><span class="count-pill">{{ $schedule->activitySessions->count() }} check-ins</span></div>
                <div class="log-list">
                    @forelse ($schedule->activitySessions->sortBy('login_time') as $classSession)
                        <article class="log-row">
                            <div class="log-info">
                                <strong>{{ $classSession->user->name }}</strong>
                                <span>{{ $classSession->user->student_or_employee_id }} · {{ $classSession->terminal->pc_name }}{{ $classSession->login_time->gt($schedule->starts_at->copy()->addMinutes(15)) ? ' · Late' : '' }}</span>
                            </div>
                            <div class="log-time">{{ $classSession->login_time->format('H:i') }}<small>{{ $classSession->logout_time ? 'Checked out '.$classSession->logout_time->format('H:i') : 'Still checked in' }}</small></div>
                        </article>
                    @empty
                        <div class="empty-state"><strong>No student check-ins</strong><p>All enrolled students are listed as absent.</p></div>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</div>
@endsection
