@extends('layouts.app')

@section('title', 'Class students')

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
            <div class="breadcrumb"><a href="{{ route('workspace.page', 'classes') }}">Classes & schedules</a> <span>/</span> <strong>Students</strong></div>
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
            @if (session('success'))
                <div class="flash flash-success" role="status">{{ session('success') }}</div>
            @endif
            @if (session('roster_not_registered'))
                <div class="flash flash-error" role="alert">
                    <strong>These CSV students are not registered yet:</strong>
                    {{ implode(', ', session('roster_not_registered')) }}.
                    They were not added. Ask them to register before importing the roster again.
                </div>
            @endif
            @if (session('roster_not_eligible'))
                <div class="flash flash-error" role="alert">
                    <strong>These CSV students have accounts but cannot be enrolled yet:</strong>
                    {{ implode(', ', session('roster_not_eligible')) }}.
                    Their accounts may be unverified, inactive, or not using an official student email.
                </div>
            @endif
            @if (session('roster_wrong_academic_group'))
                <div class="flash flash-error" role="alert">
                    <strong>These registered students do not match this class course and section ({{ $schedule->academicGroup?->label ?? 'not assigned' }}):</strong>
                    {{ implode(', ', session('roster_wrong_academic_group')) }}.
                    Update their academic group or use the correct class roster.
                </div>
            @endif
            @if ($errors->any())
                <div class="flash flash-error" role="alert">{{ $errors->first() }}</div>
            @endif
            <section class="page-heading roster-heading">
                <div><span class="eyebrow">{{ $schedule->subject_code }} · {{ $schedule->academicGroup?->label ?? $schedule->section }}</span><h1>Class students</h1><p>{{ $schedule->starts_at->format('M j, Y · H:i') }} - {{ $schedule->ends_at->format('H:i') }} · {{ $schedule->room_number }}</p></div>
                <a class="button button-secondary" href="{{ route('workspace.page', 'classes') }}">Back to classes</a>
            </section>
            <section class="panel content-panel">
                <details class="form-disclosure">
                    <summary>Update class course / section reference</summary>
                    <form method="POST" action="{{ route('schedules.academic-group.update', $schedule) }}" class="inline-form">
                        @csrf @method('PATCH')
                        <label>Admin-approved course / section<select name="academic_group_id" required>@foreach ($academicGroups as $academicGroup)<option value="{{ $academicGroup->id }}" @selected($schedule->academic_group_id === $academicGroup->id)>{{ $academicGroup->label }}</option>@endforeach</select></label>
                        <button class="button button-secondary button-small" type="submit">Save class reference</button>
                    </form>
                </details>
                <div class="panel-title-row"><div><span class="eyebrow">COURSE ROSTER</span><h2>Assigned students</h2></div><span class="count-pill">{{ $schedule->enrollments->count() }} students</span></div>
                <form method="POST" action="{{ route('schedules.students.store', $schedule) }}" class="inline-form roster-manage-form">
                    @csrf
                    <label>Student number<input name="student_id" placeholder="2026-01234-SR-0" value="{{ old('student_id') }}" required></label>
                    <label>Station<select name="terminal_id"><option value="">Any available station</option>@foreach ($terminals as $terminal)<option value="{{ $terminal->id }}">{{ $terminal->pc_name }}</option>@endforeach</select></label>
                    <button class="button button-primary button-small" type="submit">Add student</button>
                </form>
                <form method="POST" action="{{ route('schedules.students.import', $schedule) }}" class="roster-import-form" enctype="multipart/form-data">
                    @csrf
                    <label>Import official course roster (CSV)</label>
                    <input class="roster-import-input" type="file" name="roster" accept=".csv,text/csv" required>
                    <small>Include a <code>student_number</code> or <code>student_or_employee_id</code> column. Only registered, verified students assigned to {{ $schedule->academicGroup?->label ?? 'this class group' }} are added.</small>
                    <button class="button button-secondary button-small" type="button" data-upload-roster>Upload roster</button>
                </form>
                <div class="course-student-list">
                    @forelse ($schedule->enrollments as $enrollment)
                        <article class="course-student-row">
                            <a class="course-student-profile" href="{{ route('schedules.students.show', [$schedule, $enrollment->student]) }}">
                                <strong>{{ $enrollment->student->name }}</strong>
                                <span>{{ $enrollment->student->student_or_employee_id }} · {{ $enrollment->student->academicGroup?->label ?? 'Academic group not assigned' }}</span>
                            </a>
                            <form method="POST" action="{{ route('schedules.students.station.update', [$schedule, $enrollment]) }}" class="course-student-station-form">
                                @csrf @method('PATCH')
                                <label class="sr-only" for="station-{{ $enrollment->id }}">Station for {{ $enrollment->student->name }}</label>
                                <select id="station-{{ $enrollment->id }}" name="terminal_id">
                                    <option value="">No station assigned</option>
                                    @if ($enrollment->terminal && !$terminals->contains('id', $enrollment->terminal_id))
                                        <option value="{{ $enrollment->terminal_id }}" selected>{{ $enrollment->terminal->pc_name }} (current)</option>
                                    @endif
                                    @foreach ($terminals as $terminal)
                                        <option value="{{ $terminal->id }}" @selected($enrollment->terminal_id === $terminal->id)>{{ $terminal->pc_name }}</option>
                                    @endforeach
                                </select>
                                <button class="button button-secondary button-small" type="submit">Update station</button>
                            </form>
                            <form method="POST" action="{{ route('schedules.students.destroy', [$schedule, $enrollment]) }}" onsubmit="return confirm('Remove this student from the course roster?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="roster-remove">Remove</button>
                            </form>
                        </article>
                    @empty
                        <div class="empty-state"><strong>No students assigned yet</strong><p>Add a verified student by student number or import the official roster.</p></div>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</div>
@endsection
