@extends('layouts.app')

@section('title', 'Student profile')

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
            <a class="nav-link active" href="{{ route('workspace.page', 'classes') }}">Classes & schedules</a>
            <a class="nav-link" href="{{ route('workspace.page', 'session-log') }}">Session log</a>
            @if (auth()->user()->role === 'admin')
                <a class="nav-link" href="{{ route('workspace.page', 'maintenance') }}">Maintenance queue</a>
                <a class="nav-link" href="{{ route('workspace.page', 'accounts') }}">User accounts</a>
            @endif
        </nav>
        <div class="sidebar-spacer"></div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb"><a href="{{ route('schedules.students.index', $schedule) }}">Class students</a> <span>/</span> <strong>Student profile</strong></div>
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
                <div><span class="eyebrow">{{ $schedule->subject_code }} · {{ $schedule->section }}</span><h1>Student profile</h1></div>
                <a class="button button-secondary" href="{{ route('schedules.students.index', $schedule) }}">Back to students</a>
            </section>
            <div class="student-profile-grid">
                <section class="panel content-panel">
                    <span class="eyebrow">STUDENT INFORMATION</span>
                    <h2>{{ $student->name }}</h2>
                    <dl class="student-profile-details">
                        <div><dt>Student number</dt><dd>{{ $student->student_or_employee_id }}</dd></div>
                        <div><dt>Section</dt><dd>{{ $student->section ?? 'Not provided' }}</dd></div>
                        <div><dt>School webmail</dt><dd>{{ $student->email }}</dd></div>
                        <div><dt>Class attendance</dt><dd>{{ $attendance->count() }} recorded session{{ $attendance->count() === 1 ? '' : 's' }}</dd></div>
                    </dl>
                </section>
                <section class="panel content-panel">
                    <div class="panel-title-row"><div><span class="eyebrow">REGISTRATION DOCUMENT</span><h2>Certificate of registration</h2></div></div>
                    @if ($student->registration_certificate_path)
                        <img class="registration-certificate" src="{{ route('schedules.students.certificate', [$schedule, $student]) }}" alt="Certificate of registration for {{ $student->name }}">
                    @else
                        <div class="empty-state"><strong>No certificate image on file</strong></div>
                    @endif
                </section>
                <section class="panel content-panel student-attendance-panel">
                    <div class="panel-title-row"><div><span class="eyebrow">ATTENDANCE</span><h2>{{ $schedule->subject_code }} sessions</h2></div><span class="count-pill">{{ $attendance->count() }} attended</span></div>
                    <div class="log-list">
                        @forelse ($attendance as $session)
                            <article class="log-row">
                                <div class="log-info"><strong>{{ $session->login_time->format('M j, Y') }}</strong><span>{{ $session->terminal->pc_name ?? 'Station' }}</span></div>
                                <div class="log-time">{{ $session->login_time->format('H:i') }}<small>{{ $session->logout_time ? 'Checked out '.$session->logout_time->format('H:i') : 'Still checked in' }}</small></div>
                            </article>
                        @empty
                            <div class="empty-state"><strong>No attendance recorded yet</strong><p>Class check-ins will appear here.</p></div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
@endsection
