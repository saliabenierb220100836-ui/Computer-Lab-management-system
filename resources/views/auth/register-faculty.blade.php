@extends('layouts.app')

@section('title', 'Faculty registration')

@section('body')
<main class="login-page">
    <section class="login-card">
        <a class="brand brand-login" href="{{ route('login') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span>
            <span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="login-intro">
            <span class="eyebrow">FACULTY ACCOUNT</span>
            <h1>Bring your<br>class online.</h1>
            <p>Register with your employee number and official @pup.edu.ph email. We’ll send you a verification link.</p>
        </div>
        @if ($errors->any())
            <div class="flash flash-error" role="alert"><div>{{ $errors->first() }}</div></div>
        @endif
        <form class="stack-form registration-form" method="POST" action="{{ route('register.faculty.store') }}">
            @csrf
            <label for="first_name">First name</label>
            <input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" maxlength="60" required autofocus>
            <label for="last_name">Last name</label>
            <input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" maxlength="60" required>
            <label for="student_or_employee_id">Employee number</label>
            <input id="student_or_employee_id" name="student_or_employee_id" value="{{ old('student_or_employee_id') }}" placeholder="FA-1234-SR-5678" maxlength="16" pattern="FA-[0-9]{4}-SR-[0-9]{4}" title="Format: FA-####-SR-####" autocomplete="off" required>
            <label for="email">Official PUP webmail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you&#64;pup.edu.ph" autocomplete="email" maxlength="255" required>
            <p class="field-hint">Only <strong>@pup.edu.ph</strong> addresses are accepted.</p>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="16" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,16}" title="12–16 characters with uppercase, lowercase, a number, and a special character" required>
            <p class="field-hint">12–16 characters; include uppercase and lowercase letters, a number, and a special character.</p>
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="16" required>
            <button class="button button-primary button-wide" type="submit">Create faculty account</button>
        </form>
        <p class="login-foot">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
    </section>
    <aside class="login-art" aria-label="Computer lab illustration">
        <div class="art-glow"></div>
        <div class="art-copy">
            <span class="eyebrow">FOR PUP FACULTY</span>
            <h2>Teach.<br>In sync.</h2>
            <p>Use your official PUP email to verify your instructor account.</p>
        </div>
        <span class="art-caption">CAMPUS LAB · PUP</span>
    </aside>
</main>
@endsection
