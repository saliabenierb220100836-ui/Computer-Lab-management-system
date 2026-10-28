@extends('layouts.app')

@section('title', $pageTitle)

@section('body')
@php
    $roleLabels = ['admin' => 'Lab administrator', 'instructor' => 'Instructor', 'student' => 'Student'];
    $statusLabels = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'maintenance' => 'Maintenance'];
@endphp
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span><span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="side-label">WORKSPACE</div>
        <nav class="side-nav" aria-label="Main navigation">
            <a class="nav-link {{ $page === 'overview' ? 'active' : '' }}" href="{{ route('dashboard') }}">Overview</a>
            <a class="nav-link {{ $page === 'floor-map' ? 'active' : '' }}" href="{{ route('workspace.page', 'floor-map') }}">Floor map</a>
            <a class="nav-link {{ $page === 'classes' ? 'active' : '' }}" href="{{ route('workspace.page', 'classes') }}">{{ auth()->user()->role === 'student' ? 'My schedule' : 'Classes & schedules' }}</a>
            <a class="nav-link {{ $page === 'session-log' ? 'active' : '' }}" href="{{ route('workspace.page', 'session-log') }}">Session log</a>
            <a class="nav-link" href="{{ route('password.change') }}">Change password</a>
            <a class="nav-link {{ in_array($page, ['maintenance', 'fault-reports'], true) ? 'active' : '' }}" href="{{ route('workspace.page', auth()->user()->role === 'admin' ? 'maintenance' : 'fault-reports') }}">{{ auth()->user()->role === 'admin' ? 'Maintenance queue' : 'Fault reports' }}</a>
            @if (auth()->user()->role === 'admin')
                <a class="nav-link {{ $page === 'accounts' ? 'active' : '' }}" href="{{ route('workspace.page', 'accounts') }}">User accounts</a>
                <a class="nav-link {{ $page === 'academic-groups' ? 'active' : '' }}" href="{{ route('workspace.page', 'academic-groups') }}">Courses & sections</a>
            @endif
        </nav>
        <div class="sidebar-spacer"></div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Workspace <span>/</span> <strong>{{ $pageTitle }}</strong></div>
            <div class="topbar-right">
                <span class="live-pill">LAB STATUS LIVE</span>
                <span class="topbar-divider"></span>
                <div class="user-menu">
                    @if (auth()->user()->profile_photo_path)
                        <span class="avatar"><img src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}" alt=""></span>
                    @endif
                    <span><strong>{{ auth()->user()->name }}</strong><small>{{ $roleLabels[auth()->user()->role] }}</small></span>
                </div>
                <button class="logout-button" type="button" aria-haspopup="dialog" aria-controls="logout-dialog" data-open-logout-dialog>Log out</button>
            </div>
        </header>

        <dialog class="logout-dialog" id="logout-dialog" aria-labelledby="logout-dialog-title" aria-describedby="logout-dialog-description">
            <h2 id="logout-dialog-title">Sign out of PUP - SRComLab?</h2>
            <p id="logout-dialog-description">You’ll need to sign in again to access your lab workspace.</p>
            <div class="logout-dialog-actions">
                <button class="button button-secondary" type="button" data-cancel-logout>Stay signed in</button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button button-primary" type="submit">Log out</button>
                </form>
            </div>
        </dialog>

        <div class="page-content">
            @if (session('success'))
                <div class="flash flash-success" role="status">{{ session('success') }} <button type="button" onclick="this.parentElement.remove()">Dismiss</button></div>
            @endif
            @if ($errors->any())
                <div class="flash flash-error" role="alert"><div>{{ $errors->first() }}</div><button type="button" onclick="this.parentElement.remove()">Dismiss</button></div>
            @endif

            @if ($page === 'overview')
                <section class="welcome-row">
                    <div>
                        <span class="eyebrow">{{ strtoupper(now()->format('l, F j, Y')) }}</span>
                        <h1>Good {{ now()->format('H') < 12 ? 'morning' : (now()->format('H') < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }}</h1>
                        <p class="welcome-subtitle">
                            @if (auth()->user()->role === 'student') Your next great idea starts with the right seat.
                            @elseif (auth()->user()->role === 'instructor') Here's your profile and your upcoming class schedule.
                            @else Here's your lab profile and the upcoming classes using the computer lab.
                            @endif
                        </p>
                    </div>
                </section>
                <section class="overview-grid">
                    <article class="panel profile-card">
                        <span class="eyebrow">YOUR PROFILE</span>
                        <div class="profile-identity">
                            <span class="profile-avatar" @if (!auth()->user()->profile_photo_path) aria-label="Profile photo not uploaded" @endif>
                                @if (auth()->user()->profile_photo_path)
                                    <img src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}" alt="{{ auth()->user()->name }} profile photo">
                                @else
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 12a4.25 4.25 0 1 0 0-8.5 4.25 4.25 0 0 0 0 8.5Zm0 2c-4.1 0-7.5 2.1-7.5 4.75V21h15v-2.25C19.5 16.1 16.1 14 12 14Z"/>
                                    </svg>
                                @endif
                            </span>
                            <div><h2>{{ auth()->user()->name }}</h2><span>{{ $roleLabels[auth()->user()->role] }}</span></div>
                        </div>
                        <form class="profile-photo-form" method="POST" action="{{ route('profile.photo.store') }}" enctype="multipart/form-data">
                            @csrf
                            <label class="profile-photo-label" for="profile-photo">Profile photo</label>
                            <input class="profile-photo-input" id="profile-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                            <small>JPG, PNG, or WebP; saved as JPEG. Maximum file size: 2 MB.</small>
                            @error('photo') <span class="form-error">{{ $message }}</span> @enderror
                            <button class="button button-secondary button-small" type="button" data-choose-profile-photo>Upload photo</button>
                        </form>
                        <dl>
                            <div><dt>{{ auth()->user()->role === 'student' ? 'Student number' : 'Employee number' }}</dt><dd>{{ auth()->user()->student_or_employee_id }}</dd></div>
                            <div><dt>School email</dt><dd>{{ auth()->user()->email }}</dd></div>
                        </dl>
                        @if (auth()->user()->role === 'admin')
                            <details class="form-disclosure" @if ($errors->hasAny(['first_name', 'last_name'])) open @endif>
                                <summary>Change your name</summary>
                                <form method="POST" action="{{ route('admin.name-change.store') }}" class="account-create-form">
                                    @csrf
                                    <label>First name<input name="first_name" value="{{ old('first_name', auth()->user()->first_name) }}" maxlength="60" required></label>
                                    <label>Last name<input name="last_name" value="{{ old('last_name', auth()->user()->last_name) }}" maxlength="60" required></label>
                                    <p class="field-hint">We’ll send a confirmation link to {{ auth()->user()->email }}. Your name only changes after you confirm from that email.</p>
                                    <button class="button button-secondary button-small" type="submit">Send confirmation email</button>
                                </form>
                            </details>
                        @endif
                    </article>
                    <article class="panel overview-schedule">
                        <div class="panel-title-row"><div><span class="eyebrow">UP NEXT IN THE COMPUTER LAB</span><h2>{{ auth()->user()->role === 'student' ? 'Your class schedule' : 'Classes using the lab' }}</h2></div><a class="text-link" href="{{ route('workspace.page', 'classes') }}">View schedule</a></div>
                        <div class="schedule-list">
                            @forelse ($schedules->take(3) as $schedule)
                                <article class="schedule-row">
                                    <div class="schedule-date"><span>{{ strtoupper($schedule->starts_at->format('M')) }}</span><strong>{{ $schedule->starts_at->format('d') }}</strong></div>
                                    <div class="schedule-info"><strong>{{ $schedule->subject_code }} <span>{{ $schedule->section }}</span></strong><small>{{ $schedule->instructor->name }} · {{ $schedule->room_number }}</small></div>
                                    <div class="schedule-time">{{ $schedule->starts_at->format('H:i') }} - {{ $schedule->ends_at->format('H:i') }}</div>
                                </article>
                            @empty
                                <div class="empty-state"><strong>No upcoming lab classes</strong><p>Scheduled computer lab sessions will appear here.</p></div>
                            @endforelse
                        </div>
                    </article>
                </section>
            @else
                <section class="welcome-row page-heading">
                    <div><span class="eyebrow">{{ strtoupper(now()->format('l, F j, Y')) }}</span><h1>{{ $pageTitle }}</h1></div>
                </section>
            @endif

            @if ($ownSession)
                <section class="active-session-banner">
                    <div><strong>You're checked in at {{ $ownSession->terminal->pc_name }}</strong><span>Session started {{ $ownSession->login_time->format('g:i A') }} · {{ $ownSession->purpose }}</span></div>
                    <form method="POST" action="{{ route('sessions.check-out') }}">@csrf<input type="hidden" name="session" value="{{ $ownSession->id }}"><button class="button button-dark" type="submit">Check out</button></form>
                </section>
            @endif

            @if ($page === 'floor-map')
            <section class="stats-grid" aria-label="Lab status summary">
                @foreach ([
                    ['available', 'PCs available', 'Ready to use'],
                    ['occupied', 'In session', 'Stations in active use'],
                    ['reserved', 'Class reserved', 'Stations set aside for class'],
                    ['maintenance', 'Needs attention', 'Stations being looked after'],
                ] as [$key, $label, $caption])
                    <article class="stat-card stat-{{ $key }}">
                        <div class="stat-top"><span>{{ $label }}</span></div>
                        <div class="stat-count">
                            @if ($key === 'available')
                                {{ $counts[$key] }}<span>/{{ $terminals->count() }}</span>
                            @else
                                {{ str_pad((string) $counts[$key], 2, '0', STR_PAD_LEFT) }}
                            @endif
                        </div>
                        <div class="stat-caption">{{ $caption }}</div>
                    </article>
                @endforeach
            </section>
            @endif

            @if ($page === 'floor-map')
            <section class="panel map-panel" id="floor-map">
                <div class="section-heading">
                    <div><span class="eyebrow">YOUR SPACE, AT A GLANCE</span><h2>Lab floor map</h2><p>Choose a station to see what's happening.</p></div>
                    <span class="map-location">{{ $schedules->first()?->room_number ?? 'Lab 01' }}</span>
                </div>
                <div class="map-toolbar">
                    <div class="map-legend">
                        @foreach (['available', 'occupied', 'reserved', 'maintenance'] as $state)
                            <span class="legend-{{ $state }}">{{ $statusLabels[$state] }}</span>
                        @endforeach
                    </div>
                    <span class="map-dimensions">40 STATIONS <i>·</i> LIVE VIEW</span>
                </div>
                <div class="floor-layout">
                    <div class="floor-map" role="list" aria-label="Interactive computer lab floor map">
                        @foreach ($terminals as $terminal)
                            @php
                                $status = $statusFor($terminal);
                                $isAssignedStudent = $terminal->activeReservation?->user_id === auth()->id();
                                $canCheckIn = auth()->user()->role === 'student'
                                    && !$ownSession
                                    && in_array($status, ['available', 'reserved'], true)
                                    && ($status !== 'reserved' || $isAssignedStudent);
                            @endphp
                            <article class="station-card station-{{ $status }} {{ $isAssignedStudent ? 'station-mine' : '' }}" role="listitem" style="grid-column: {{ $terminal->grid_x }}; grid-row: {{ $terminal->grid_y }}" title="{{ $terminal->specs_description }}">
                                <span class="station-name">{{ $terminal->pc_name }}</span>
                                <span class="station-status">{{ $statusLabels[$status] }}</span>
                                @if (auth()->user()->role === 'instructor' && $terminal->activeSession?->schedule?->instructor_id === auth()->id())
                                    <span class="station-detail station-student-identity">{{ $terminal->activeSession->user->student_or_employee_id }} · {{ $terminal->activeSession->user->name }}</span>
                                @elseif ($terminal->activeSession)
                                    <span class="station-detail">Session in progress</span>
                                @elseif ($status === 'reserved')
                                    <span class="station-detail">{{ $terminal->activeReservation->schedule->subject_code }}</span>
                                @endif
                                @if ($canCheckIn)
                                    <form method="POST" action="{{ route('terminals.check-in', $terminal) }}" class="station-action">
                                        @csrf<input type="hidden" name="purpose" value="{{ old('purpose', 'Open lab') }}">
                                        <button type="submit" aria-label="Check in to {{ $terminal->pc_name }}">Check in</button>
                                    </form>
                                @endif
                                @if (auth()->user()->role === 'admin')
                                    @if ($terminal->activeSession)
                                        <form method="POST" action="{{ route('terminals.force-release', $terminal) }}" class="station-action" onsubmit="return confirm(@js('Force checkout from '.$terminal->pc_name.'? This will end the active session.'))">
                                            @csrf
                                            <button type="submit">Force release</button>
                                        </form>
                                    @endif
                                    <details class="station-edit">
                                        <summary>Edit station</summary>
                                        <form method="POST" action="{{ route('terminals.update', $terminal) }}" class="compact-form">
                                            @csrf @method('PATCH')
                                            <label>Station name<input name="pc_name" value="{{ $terminal->pc_name }}" required></label>
                                            <label>Base status<select name="status"><option value="available" @selected($terminal->status === 'available')>Ready</option><option value="maintenance" @selected($terminal->status === 'maintenance')>Maintenance</option></select></label>
                                            <button class="button button-primary button-small" type="submit">Save station</button>
                                        </form>
                                    </details>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
            @endif

                @if ($page === 'classes')
                <section class="panel content-panel" id="classes">
                    <div class="panel-title-row"><div><span class="eyebrow">WHAT'S ON THE CALENDAR</span><h2>Classes & schedules</h2></div><span class="count-pill">{{ $schedules->count() }} upcoming</span></div>
                    @if (in_array(auth()->user()->role, ['admin', 'instructor'], true))
                        <details class="form-disclosure" id="schedule-form" @if ($errors->any()) open @endif>
                            <summary>+ Schedule a lab slot</summary>
                            <form method="POST" action="{{ route('schedules.store') }}" class="inline-form schedule-form">
                                @csrf
                                <label>Subject code<input name="subject_code" placeholder="CS 201" value="{{ old('subject_code') }}" maxlength="30" required></label>
                                <label>Course / section<select name="academic_group_id" required><option value="">Select a course and section</option>@foreach ($selectableAcademicGroups as $academicGroup)<option value="{{ $academicGroup->id }}" @selected(old('academic_group_id') == $academicGroup->id)>{{ $academicGroup->course }} · {{ $academicGroup->section }}</option>@endforeach</select></label>
                                <label>Room<input name="room_number" value="{{ old('room_number', 'Lab 01') }}" maxlength="40" required></label>
                                <label>Starts<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" min="{{ now()->format('Y-m-d\TH:i') }}" required></label>
                                <label>Ends<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required></label>
                                <button class="button button-primary button-small" type="submit">Create schedule</button>
                            </form>
                        </details>
                    @endif
                    @if (auth()->user()->role === 'instructor')
                        <details class="form-disclosure" @if ($errors->has('reason')) open @endif>
                            <summary>+ Request a missing course or section</summary>
                            <p class="panel-description">Requests go to the administrator. You can schedule a class after the course and section are added to the catalog.</p>
                            <form method="POST" action="{{ route('academic-group-requests.store') }}" class="account-create-form">
                                @csrf
                                <label>Course / department<input name="course" value="{{ old('course') }}" maxlength="80" placeholder="BSIT" required></label>
                                <label>Section<input name="section" value="{{ old('section') }}" maxlength="60" placeholder="BSIT 3-1" required></label>
                                <label>Reason (optional)<input name="reason" value="{{ old('reason') }}" maxlength="1000" placeholder="Explain why this section is needed"></label>
                                <button class="button button-secondary button-small" type="submit">Send request</button>
                            </form>
                        </details>
                        @if ($academicGroupRequests->isNotEmpty())
                            <div class="dismissed-classes">
                                <div class="panel-title-row"><div><h2>Your section requests</h2></div></div>
                                <div class="log-list">
                                    @foreach ($academicGroupRequests as $groupRequest)
                                        <article class="log-row">
                                            <div class="log-info">
                                                <strong>{{ $groupRequest->course }} · {{ $groupRequest->section }}</strong>
                                                <span>{{ $groupRequest->reason ?: 'No reason provided' }}</span>
                                                @if ($groupRequest->review_note)
                                                    <small>Admin note: {{ $groupRequest->review_note }}</small>
                                                @endif
                                            </div>
                                            <span class="ticket-badge badge-{{ $groupRequest->status }}">{{ ucfirst($groupRequest->status) }}</span>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                    <div class="schedule-list">
                        @forelse ($schedules as $schedule)
                            <article class="schedule-row">
                                <div class="schedule-date"><span>{{ strtoupper($schedule->starts_at->format('M')) }}</span><strong>{{ $schedule->starts_at->format('d') }}</strong></div>
                                <div class="schedule-info"><strong>{{ $schedule->subject_code }} <span>{{ $schedule->academicGroup?->label ?? $schedule->section }}</span></strong><small>{{ $schedule->instructor->name }} · {{ $schedule->room_number }}</small></div>
                                <div class="schedule-time">{{ $schedule->starts_at->format('H:i') }} - {{ $schedule->ends_at->format('H:i') }}</div>
                                @if (in_array(auth()->user()->role, ['admin', 'instructor'], true))
                                    <a class="button button-secondary button-small" href="{{ route('schedules.students.index', $schedule) }}">Students ({{ $schedule->enrollments->count() }})</a>
                                    @if ($schedule->starts_at->isFuture())
                                        <details class="form-disclosure schedule-edit" @if (old('edit_schedule_id') == $schedule->id) open @endif>
                                            <summary>Edit class</summary>
                                            <form method="POST" action="{{ route('schedules.update', $schedule) }}" class="inline-form schedule-form">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="edit_schedule_id" value="{{ $schedule->id }}">
                                                <label>Subject code<input name="subject_code" value="{{ old('subject_code', $schedule->subject_code) }}" maxlength="30" required></label>
                                                <label>Starts<input type="datetime-local" name="starts_at" value="{{ old('starts_at', $schedule->starts_at->format('Y-m-d\TH:i')) }}" min="{{ now()->format('Y-m-d\TH:i') }}" required></label>
                                                <label>Ends<input type="datetime-local" name="ends_at" value="{{ old('ends_at', $schedule->ends_at->format('Y-m-d\TH:i')) }}" required></label>
                                                <button class="button button-primary button-small" type="submit">Save changes</button>
                                            </form>
                                        </details>
                                        <form method="POST" action="{{ route('schedules.start', $schedule) }}">
                                            @csrf
                                            <button class="button button-primary button-small" type="submit">Start class</button>
                                        </form>
                                        <form method="POST" action="{{ route('schedules.cancel', $schedule) }}" onsubmit="return confirm('Cancel this class schedule? Enrolled students will be removed from this class.')">
                                            @csrf @method('DELETE')
                                            <button class="button button-danger button-small" type="submit">Cancel class</button>
                                        </form>
                                    @elseif (!$schedule->dismissed_at)
                                        <form method="POST" action="{{ route('schedules.dismiss', $schedule) }}" onsubmit="return confirm('Dismiss this class and finalize its attendance summary?')">
                                            @csrf
                                            <button class="button button-secondary button-small" type="submit">Dismiss class</button>
                                        </form>
                                    @else
                                        <span class="count-pill">Class dismissed</span>
                                    @endif
                                @endif
                            </article>
                        @empty
                            <div class="empty-state"><strong>No classes scheduled yet</strong><p>New lab sessions will show up here.</p></div>
                        @endforelse
                    </div>
                </section>
                @endif

                @if ($page === 'session-log')
                <section class="panel content-panel" id="activity">
                    @if (auth()->user()->role === 'admin')
                        <a class="dismissed-class-card active-users-card" href="{{ route('workspace.page', 'active-users') }}">
                            <span class="dismissed-class-heading">
                                <span><strong>Currently logged in</strong><span>View accounts active on the website and their PC check-in status.</span></span>
                                <span>{{ $loggedInUsers->count() }} active · View accounts</span>
                            </span>
                        </a>
                    @endif
                    @if (auth()->user()->role === 'admin')
                        <div class="dismissed-classes">
                            <div class="panel-title-row"><div><h2>Session logs by date</h2></div></div>
                            <div class="session-log-date-list">
                                @forelse ($sessionLogDates as $sessionLogDate)
                                    @php $logDate = \Illuminate\Support\Carbon::parse($sessionLogDate->session_date); @endphp
                                    <a class="dismissed-class-card session-log-date-card" href="{{ route('admin.session-logs.show', $logDate->format('Y-m-d')) }}">
                                        <span class="dismissed-class-heading">
                                            <span><strong>{{ $logDate->format('F j, Y') }}</strong><span>Daily lab session records</span></span>
                                            <span>{{ $sessionLogDate->session_count }} {{ \Illuminate\Support\Str::plural('session', $sessionLogDate->session_count) }} · View logs</span>
                                        </span>
                                    </a>
                                @empty
                                    <div class="empty-state"><strong>No session logs yet</strong><p>Lab sessions will be grouped here by date.</p></div>
                                @endforelse
                            </div>
                        </div>
                    @elseif (auth()->user()->role === 'student')
                        <div class="panel-title-row"><div><h2>Session log</h2></div></div>
                        <div class="log-list">
                            @forelse ($recentSessions as $session)
                                @php
                                    $canSeeStudentIdentity = auth()->user()->role === 'admin'
                                        || (auth()->user()->role === 'instructor' && $session->schedule?->instructor_id === auth()->id());
                                @endphp
                                <article class="log-row">
                                    <div class="log-info"><strong>{{ $canSeeStudentIdentity ? $session->user->name : 'Lab session' }}</strong><span>{{ $canSeeStudentIdentity ? $session->user->student_or_employee_id.' · ' : '' }}{{ $session->terminal->pc_name }} · {{ $session->schedule?->subject_code ?? $session->purpose }}</span></div>
                                    <div class="log-time">{{ $session->login_time->format('g:i A') }}<small>{{ $session->logout_time ? 'Checked out '.$session->logout_time->format('g:i A') : 'In session' }}</small></div>
                                </article>
                            @empty
                                <div class="empty-state"><strong>No sessions logged yet</strong></div>
                            @endforelse
                        </div>
                    @endif
                    @if (auth()->user()->role !== 'student')
                        <div class="dismissed-classes">
                            <div class="panel-title-row"><div><h2>Completed classes</h2></div><span class="count-pill">{{ $dismissedSchedules->count() }} completed</span></div>
                            @forelse ($dismissedSchedules as $schedule)
                                @php $attendanceSummary = $attendanceSummaries[$schedule->id]; @endphp
                                <a class="dismissed-class-card" href="{{ route('schedules.attendance.show', $schedule) }}">
                                    <span class="dismissed-class-heading">
                                        <span><strong>{{ $schedule->subject_code }} · {{ $schedule->section }}</strong><span>{{ $schedule->starts_at->format('M j, Y · H:i') }} · {{ $schedule->instructor->name }}</span></span>
                                        <span>Duration: {{ $attendanceSummary['duration'] }} · {{ $schedule->activitySessions->count() }} check-ins · View class log</span>
                                    </span>
                                </a>
                            @empty
                                <div class="empty-state"><strong>No dismissed classes yet</strong><p>Dismissed class attendance summaries will appear here.</p></div>
                            @endforelse
                        </div>
                    @endif
                </section>
                @endif

                @if ($page === 'active-users' && auth()->user()->role === 'admin')
                    <section class="page-heading roster-heading">
                        <div>
                            <span class="eyebrow">ACTIVE WEBSITE SESSIONS</span>
                            <h1>Currently logged in</h1>
                            <p>{{ $loggedInUsers->count() }} active {{ \Illuminate\Support\Str::plural($activeUserRole === 'student' ? 'student' : 'faculty member', $loggedInUsers->count()) }}</p>
                        </div>
                        <a class="button button-secondary" href="{{ route('workspace.page', 'session-log') }}">Back to session log</a>
                    </section>
                    <section class="panel content-panel" id="active-users">
                        <div class="panel-title-row"><div><span class="eyebrow">ACTIVE ACCOUNTS</span><h2>Website logins</h2></div><span class="count-pill">{{ $loggedInUsers->count() }} active</span></div>
                        <nav class="active-user-tabs" aria-label="Filter active accounts">
                            <a class="{{ $activeUserRole === 'student' ? 'active' : '' }}" href="{{ route('workspace.page', ['page' => 'active-users', 'role' => 'student']) }}" @if ($activeUserRole === 'student') aria-current="page" @endif>
                                Students <span>{{ $activeUserCounts['student'] }}</span>
                            </a>
                            <a class="{{ $activeUserRole === 'instructor' ? 'active' : '' }}" href="{{ route('workspace.page', ['page' => 'active-users', 'role' => 'instructor']) }}" @if ($activeUserRole === 'instructor') aria-current="page" @endif>
                                Faculty <span>{{ $activeUserCounts['instructor'] }}</span>
                            </a>
                        </nav>
                        <div class="log-list">
                            @forelse ($loggedInUsers as $loggedInUser)
                                @php $checkedInSession = $checkedInSessions->get($loggedInUser->id); @endphp
                                <article class="log-row">
                                    <div class="log-info">
                                        <strong>{{ $loggedInUser->name }}</strong>
                                        <span>{{ $loggedInUser->student_or_employee_id }} · {{ ucfirst($loggedInUser->role) }}</span>
                                    </div>
                                    <div class="log-time">{{ $checkedInSession?->terminal?->pc_name ?? 'Not checked into a PC' }}<small>{{ $checkedInSession ? 'Checked in at '.$checkedInSession->login_time->format('g:i A') : 'Website session active' }}</small></div>
                                </article>
                            @empty
                                <div class="empty-state"><strong>No {{ $activeUserRole === 'student' ? 'students' : 'faculty members' }} are currently logged in</strong></div>
                            @endforelse
                        </div>
                    </section>
                @endif

            @if ($page === 'accounts' && auth()->user()->role === 'admin')
                <section class="panel content-panel accounts-panel" id="accounts">
                    <div class="panel-title-row"><div><span class="eyebrow">PEOPLE WITH LAB ACCESS</span><h2>User account management</h2></div><span class="count-pill">{{ $managedAccountCounts[$accountRole] }} {{ $accountRole === 'student' ? 'students' : 'faculty' }}</span></div>
                    <p class="panel-description">Create, review, update, and deactivate instructor and student accounts. Deactivated users can no longer sign in; attendance history is retained.</p>
                    <nav class="active-user-tabs" aria-label="Filter user accounts">
                        <a class="{{ $accountRole === 'student' ? 'active' : '' }}" href="{{ route('workspace.page', ['page' => 'accounts', 'role' => 'student']) }}" @if ($accountRole === 'student') aria-current="page" @endif>
                            Students <span>{{ $managedAccountCounts['student'] }}</span>
                        </a>
                        <a class="{{ $accountRole === 'instructor' ? 'active' : '' }}" href="{{ route('workspace.page', ['page' => 'accounts', 'role' => 'instructor']) }}" @if ($accountRole === 'instructor') aria-current="page" @endif>
                            Faculty <span>{{ $managedAccountCounts['instructor'] }}</span>
                        </a>
                    </nav>
                    <details class="form-disclosure" @if ($errors->hasAny(['certificate', 'role', 'first_name', 'last_name', 'student_or_employee_id', 'email', 'academic_group_id', 'password'])) open @endif>
                        <summary>+ Create an instructor or student account</summary>
                        <form method="POST" action="{{ route('admin.users.store') }}" class="account-create-form" enctype="multipart/form-data" data-admin-account-form>
                            @csrf
                            <label>First name<input name="first_name" maxlength="60" required></label>
                            <label>Last name<input name="last_name" maxlength="60" required></label>
                            <label>Student / employee number<input name="student_or_employee_id" maxlength="16" pattern="([0-9]{4}-[0-9]{5}-SR-0|FA-[0-9]{4}-SR-[0-9]{4})" title="Student: YYYY-#####-SR-0 · Employee: FA-####-SR-####" placeholder="2026-01234-SR-0 or FA-1234-SR-5678" required></label>
                            <label>Email<input type="email" name="email" maxlength="255" required></label>
                            <p class="field-hint">Students must use {{ '@'.config('app.student_email_domain') }}; employees/faculty must use @pup.edu.ph.</p>
                            <label>Role<select name="role" required data-admin-account-role><option value="student" @selected(old('role', 'student') === 'student')>Student</option><option value="instructor" @selected(old('role') === 'instructor')>Instructor</option></select></label>
                            <label>Course / section (students)<select name="academic_group_id"><option value="">Select a student course / section</option>@foreach ($selectableAcademicGroups as $academicGroup)<option value="{{ $academicGroup->id }}">{{ $academicGroup->label }}</option>@endforeach</select></label>
                            <label>Password<input type="password" name="password" minlength="12" maxlength="16" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,16}" title="12–16 characters with uppercase, lowercase, a number, and a special character" autocomplete="new-password" required></label>
                            <label>Confirm password<input type="password" name="password_confirmation" minlength="12" maxlength="16" autocomplete="new-password" required></label>
                            <label class="account-certificate-field" data-student-certificate-field>Certificate of registration image<input type="file" name="certificate" accept="image/jpeg,image/png,image/webp" required data-student-certificate><small class="field-hint">Required for student accounts. JPG, PNG, or WebP; maximum 10 MB.</small></label>
                            <p class="field-hint">Passwords need 12–16 characters, with uppercase and lowercase letters, a number, and a special character.</p>
                            <p class="field-hint">Accounts created by an administrator are marked as verified.</p>
                            <button class="button button-primary button-small" type="submit">Create account</button>
                        </form>
                    </details>
                    <div class="accounts-list">
                        @forelse ($managedUsers as $managedUser)
                            <article class="account-row">
                                <div class="account-person"><div><strong>{{ $managedUser->name }}</strong><span>{{ $managedUser->student_or_employee_id }}</span></div></div>
                                <span class="account-email">{{ $managedUser->email }}</span>
                                <span class="account-role role-{{ $managedUser->role }}">{{ ucfirst($managedUser->role) }}</span>
                                <span class="account-verification {{ $managedUser->hasVerifiedEmail() ? 'is-verified' : 'is-pending' }}">{{ $managedUser->hasVerifiedEmail() ? 'Verified' : 'Pending email' }}</span>
                                <details class="account-edit">
                                    <summary>Edit</summary>
                                    <form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="account-edit-form">
                                        @csrf @method('PATCH')
                                        <label>First name<input name="first_name" value="{{ $managedUser->first_name }}" maxlength="60" required></label>
                                        <label>Last name<input name="last_name" value="{{ $managedUser->last_name }}" maxlength="60" required></label>
                                        <label>Student / employee number<input name="student_or_employee_id" value="{{ $managedUser->student_or_employee_id }}" maxlength="16" pattern="([0-9]{4}-[0-9]{5}-SR-0|FA-[0-9]{4}-SR-[0-9]{4})" title="Student: YYYY-#####-SR-0 · Employee: FA-####-SR-####" placeholder="2026-01234-SR-0 or FA-1234-SR-5678" required></label>
                                        <label>Email<input type="email" name="email" value="{{ $managedUser->email }}" maxlength="255" required></label>
                                        <span class="field-hint">Student: {{ '@'.config('app.student_email_domain') }} · Employee/faculty: @pup.edu.ph</span>
                                        <label>Role<select name="role"><option value="student" @selected($managedUser->role === 'student')>Student</option><option value="instructor" @selected($managedUser->role === 'instructor')>Instructor</option></select></label>
                                        <label>Course / section (students)<select name="academic_group_id"><option value="">Select a student course / section</option>@foreach ($selectableAcademicGroups as $academicGroup)<option value="{{ $academicGroup->id }}" @selected((int) $managedUser->academic_group_id === $academicGroup->id)>{{ $academicGroup->label }}</option>@endforeach</select></label>
                                        <button class="button button-primary button-small" type="submit">Save changes</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" onsubmit="return confirm(@js('Deactivate '.$managedUser->name.'? Their historic attendance will be retained.'))">
                                    @csrf @method('DELETE')
                                    <button class="account-delete" type="submit">Deactivate</button>
                                </form>
                            </article>
                        @empty
                            <div class="empty-state"><strong>No {{ $accountRole === 'student' ? 'student' : 'faculty' }} accounts yet</strong><p>Create an account or invite students to register.</p></div>
                        @endforelse
                    </div>
                    {{ $managedUsers->links() }}
                </section>
            @endif

            @if (in_array($page, ['maintenance', 'fault-reports'], true))
            <section class="panel content-panel tickets-panel" id="tickets">
                <div class="panel-title-row"><div><span class="eyebrow">{{ auth()->user()->role === 'admin' ? 'KEEPING THINGS IN GOOD SHAPE' : 'WE’RE HERE TO HELP' }}</span><h2>{{ auth()->user()->role === 'admin' ? 'Maintenance queue' : 'Fault reports' }}</h2></div><span class="count-pill">{{ $tickets->count() }} recent</span></div>
                <details class="form-disclosure">
                    <summary>+ Report a station issue</summary>
                    <form method="POST" action="{{ route('tickets.store') }}" class="inline-form ticket-form">
                        @csrf
                        <label>Station<select name="terminal_id" required>@foreach ($terminals as $terminal)<option value="{{ $terminal->id }}">{{ $terminal->pc_name }}</option>@endforeach</select></label>
                        <label>Equipment<select name="issue_type" required>@foreach (['Monitor', 'Keyboard', 'Mouse', 'Network', 'Power', 'Other'] as $issue)<option>{{ $issue }}</option>@endforeach</select></label>
                        <label class="description-field">What happened?<input name="description" placeholder="A short description helps us fix it faster" maxlength="2000" required></label>
                        <button class="button button-primary button-small" type="submit">Send report</button>
                    </form>
                </details>
                <div class="ticket-list">
                    @forelse ($tickets as $ticket)
                        <article class="ticket-row">
                            <div class="ticket-main"><strong>{{ $ticket->issue_type }} · {{ $ticket->terminal->pc_name }}</strong><span>{{ $ticket->description }}</span><small>Reported by {{ $ticket->reporter->name }} · {{ $ticket->created_at->diffForHumans() }}</small></div>
                            @if (auth()->user()->role === 'admin')
                                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="ticket-status-form">
                                    @csrf @method('PATCH')
                                    <select name="priority" aria-label="Ticket priority">@foreach (['low', 'normal', 'high', 'urgent'] as $priority)<option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ ucfirst($priority) }} priority</option>@endforeach</select>
                                    <select name="status" aria-label="Ticket status">@foreach (['open', 'in_progress', 'resolved'] as $status)<option value="{{ $status }}" @selected($ticket->status === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select>
                                    <button class="button button-secondary button-small" type="submit">Update</button>
                                </form>
                            @else
                                <span class="ticket-badge badge-{{ $ticket->status }}">{{ str_replace('_', ' ', $ticket->status) }}</span>
                            @endif
                        </article>
                    @empty
                        <div class="empty-state"><strong>All quiet on the lab front</strong><p>New equipment reports will show up here.</p></div>
                    @endforelse
                </div>
            </section>
            @endif
            @if ($page === 'academic-groups' && auth()->user()->role === 'admin')
                <section class="page-heading roster-heading">
                    <div>
                        <span class="eyebrow">ACADEMIC CATALOG</span>
                        <h1>Courses & sections</h1>
                        <p>Manage the course and section options students and instructors can select.</p>
                    </div>
                    <a class="button button-secondary" href="{{ route('admin.academic-groups.template') }}">Download CSV template</a>
                </section>
                <section class="panel content-panel accounts-panel" id="academic-groups">
                    <div class="panel-title-row">
                        <div><span class="eyebrow">STUDENT GROUP REFERENCES</span><h2>Course and section list</h2></div>
                        <span class="count-pill">{{ $academicGroups->count() }} groups</span>
                    </div>
                    <p class="panel-description">Use a CSV with the header <code>course,section</code>. Sections must match the course and use <code>COURSE #-#</code>, such as <code>BSIT 3-1</code>. Existing entries and repeated rows are skipped during import.</p>
                    <details class="form-disclosure" @if ($errors->has('course') || $errors->has('section')) open @endif>
                        <summary>+ Add a course and section</summary>
                        <form method="POST" action="{{ route('admin.academic-groups.store') }}" class="account-create-form">
                            @csrf
                            <label>Course / department<input name="course" value="{{ old('course') }}" maxlength="80" placeholder="BSIT" required></label>
                            <label>Section<input name="section" value="{{ old('section') }}" maxlength="60" placeholder="BSIT 3-1" required></label>
                            <button class="button button-primary button-small" type="submit">Add to catalog</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('admin.academic-groups.import') }}" enctype="multipart/form-data" class="roster-import-form">
                        @csrf
                        <label for="academic-groups-file">Upload course and section list</label>
                        <input id="academic-groups-file" type="file" name="file" accept=".csv,text/csv" required>
                        <small>CSV only, up to 2 MB and 1,000 distinct rows. Required columns: <code>course,section</code>. Sections must match the course and use <code>COURSE #-#</code>, such as <code>BSIT 3-1</code>.</small>
                        <button class="button button-secondary button-small" type="submit">Import CSV</button>
                    </form>
                    <div class="dismissed-classes">
                        <div class="panel-title-row">
                            <div><span class="eyebrow">INSTRUCTOR REQUESTS</span><h2>Pending course and section requests</h2></div>
                            <span class="count-pill">{{ $academicGroupRequests->count() }} pending</span>
                        </div>
                        @forelse ($academicGroupRequests as $groupRequest)
                            <article class="ticket-row">
                                <div class="ticket-main">
                                    <strong>{{ $groupRequest->course }} · {{ $groupRequest->section }}</strong>
                                    <span>{{ $groupRequest->reason ?: 'No reason provided.' }}</span>
                                    <small>Requested by {{ $groupRequest->requester?->name ?? 'Former instructor' }} · {{ $groupRequest->created_at->diffForHumans() }}</small>
                                </div>
                                <div class="ticket-status-form">
                                    <form method="POST" action="{{ route('admin.academic-group-requests.approve', $groupRequest) }}">
                                        @csrf
                                        <button class="button button-primary button-small" type="submit">Approve & add</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.academic-group-requests.reject', $groupRequest) }}">
                                        @csrf
                                        <label class="sr-only" for="review-note-{{ $groupRequest->id }}">Reason for rejecting request</label>
                                        <input id="review-note-{{ $groupRequest->id }}" name="review_note" maxlength="1000" placeholder="Optional note">
                                        <button class="button button-secondary button-small" type="submit">Reject</button>
                                    </form>
                                </div>
                            </article>
                        @empty
                            <div class="empty-state"><strong>No pending section requests</strong><p>Instructor requests will appear here for review.</p></div>
                        @endforelse
                    </div>
                    <div class="log-list">
                        @forelse ($academicGroups as $academicGroup)
                            <article class="log-row">
                                <div class="log-info">
                                    <strong>{{ $academicGroup->course }} · {{ $academicGroup->section }}</strong>
                                    <span>{{ $academicGroup->students_count }} student(s) · {{ $academicGroup->schedules_count }} class schedule(s)</span>
                                    @unless ($academicGroup->hasValidSectionFormat())
                                        <small>This legacy entry is not selectable. Edit it to match {{ $academicGroup->course }} #-#.</small>
                                    @endunless
                                </div>
                                <details class="account-edit">
                                    <summary>Edit</summary>
                                    <form method="POST" action="{{ route('admin.academic-groups.update', $academicGroup) }}" class="account-edit-form">
                                        @csrf @method('PATCH')
                                        <label>Course / department<input name="course" value="{{ $academicGroup->course }}" maxlength="80" required></label>
                                        <label>Section<input name="section" value="{{ $academicGroup->section }}" maxlength="60" placeholder="{{ $academicGroup->course }} 3-1" required></label>
                                        <button class="button button-primary button-small" type="submit">Save changes</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.academic-groups.destroy', $academicGroup) }}" onsubmit="return confirm(@js('Delete '.$academicGroup->course.' · '.$academicGroup->section.'?'))">
                                    @csrf @method('DELETE')
                                    <button class="account-delete" type="submit">Delete</button>
                                </form>
                            </article>
                        @empty
                            <div class="empty-state"><strong>No course or section groups yet</strong><p>Add one above or import your CSV list.</p></div>
                        @endforelse
                    </div>
                </section>
            @endif
            <footer class="page-footer"><span>PUP - SRComLab <i>·</i> MADE FOR BETTER LAB DAYS</span><span>Need a hand? Talk to your lab technician.</span></footer>
        </div>
    </main>
</div>
@endsection
