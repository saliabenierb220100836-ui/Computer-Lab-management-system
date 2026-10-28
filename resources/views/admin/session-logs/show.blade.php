@extends('layouts.app')

@section('title', 'Session logs · '.$date->format('F j, Y'))

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
            <a class="nav-link" href="{{ route('workspace.page', 'maintenance') }}">Maintenance queue</a>
            <a class="nav-link" href="{{ route('workspace.page', 'accounts') }}">User accounts</a>
        </nav>
        <div class="sidebar-spacer"></div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb"><a href="{{ route('workspace.page', 'session-log') }}">Session log</a> <span>/</span> <strong>{{ $date->format('F j, Y') }}</strong></div>
            <div class="topbar-right">
                <div class="user-menu"><span><strong>{{ auth()->user()->name }}</strong><small>Lab administrator</small></span></div>
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
                    <span class="eyebrow">DAILY LAB ACTIVITY</span>
                    <h1>Session logs</h1>
                    <p>{{ $date->format('l, F j, Y') }} · {{ $sessions->count() }} {{ \Illuminate\Support\Str::plural('session', $sessions->count()) }}</p>
                </div>
                <div class="completed-class-actions">
                    <a class="button button-secondary" href="{{ route('workspace.page', 'session-log') }}">Back to session log</a>
                    <a class="button button-primary" href="{{ route('admin.session-logs.export', $date->format('Y-m-d')) }}">Export CSV</a>
                </div>
            </section>
            <section class="panel content-panel">
                <div class="panel-title-row"><div><span class="eyebrow">CHECK-INS</span><h2>Lab sessions</h2></div><span class="count-pill">{{ $sessions->count() }} records</span></div>
                <div class="log-list">
                    @forelse ($sessions as $session)
                        <article class="log-row">
                            <div class="log-info">
                                <strong>{{ $session->user->name }}</strong>
                                <span>{{ $session->user->student_or_employee_id }} · {{ ucfirst($session->user->role) }} · {{ $session->terminal->pc_name }} · {{ $session->schedule?->subject_code ?? $session->purpose }}</span>
                            </div>
                            <div class="log-time">{{ $session->login_time->format('g:i A') }}<small>{{ $session->logout_time ? 'Checked out '.$session->logout_time->format('g:i A') : 'Still checked in' }}</small></div>
                        </article>
                    @empty
                        <div class="empty-state"><strong>No lab sessions recorded for this date</strong></div>
                    @endforelse
                </div>
            </section>
            <footer class="page-footer"><span>PUP - SRComLab <i>·</i> MADE FOR BETTER LAB DAYS</span><span>Need a hand? Talk to your lab technician.</span></footer>
        </div>
    </main>
</div>
@endsection
