@extends('layouts.app')

@section('title', 'Student registration')

@section('body')
<main class="login-page">
    <section class="login-card">
        <a class="brand brand-login" href="{{ route('login') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span>
            <span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="login-intro">
            <span class="eyebrow">STUDENT ACCOUNT</span>
            <h1>Make room<br>for what’s next.</h1>
            <p>Register with your PUP student number and official school email. We’ll send you a verification link.</p>
        </div>
        @if ($errors->any())
            <div class="flash flash-error" role="alert"><div>{{ $errors->first() }}</div></div>
        @endif
        <form class="stack-form registration-form" method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data">
            @csrf
            <label for="first_name">First name</label>
            <input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" maxlength="60" required autofocus>
            <label for="last_name">Last name</label>
            <input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" maxlength="60" required>
            <label for="academic_group_id">Course / department and section</label>
            <select id="academic_group_id" name="academic_group_id" required>
                <option value="">Select your course and section</option>
                @foreach ($academicGroups as $academicGroup)
                    <option value="{{ $academicGroup->id }}" @selected(old('academic_group_id') == $academicGroup->id)>{{ $academicGroup->label }}</option>
                @endforeach
            </select>
            @if ($academicGroups->isEmpty())
                <p class="field-hint">Your instructor must schedule a class for your course and section before registration is available.</p>
            @endif
            <label for="student_or_employee_id">Student number</label>
            <input id="student_or_employee_id" name="student_or_employee_id" value="{{ old('student_or_employee_id') }}" placeholder="2026-01234-SR-0" maxlength="16" pattern="[0-9]{4}-[0-9]{5}-SR-0" title="Format: YYYY-#####-SR-0 (example: 2026-01234-SR-0)" autocomplete="off" required>
            <label for="email">Official school email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you&#64;{{ $emailDomain }}" autocomplete="email" maxlength="255" required>
            <p class="field-hint">Only <strong>{{ '@'.$emailDomain }}</strong> addresses are accepted.</p>
            <label for="certificate">Certificate of registration image</label>
            <input id="certificate" type="file" name="certificate" accept="image/jpeg,image/png,image/webp" required>
            <p class="field-hint">Upload a JPG, PNG, or WebP image (maximum 5 MB). Only instructors assigned to your class can view it.</p>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="16" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,16}" title="12–16 characters with uppercase, lowercase, a number, and a special character" required>
            <p class="field-hint">12–16 characters; include uppercase and lowercase letters, a number, and a special character.</p>
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="16" required>
            <button class="button button-primary button-wide" type="submit">Create student account</button>
        </form>
        <p class="login-foot">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
    </section>
    <aside class="login-art" aria-label="Computer lab illustration">
        <div class="art-glow"></div>
        <div class="art-copy">
            <span class="eyebrow">YOUR CAMPUS. YOUR LAB.</span>
            <h2>One less<br>thing to worry about.</h2>
            <p>Use your official student email to verify your account and get started.</p>
        </div>
        <span class="art-caption">CAMPUS LAB · PUP</span>
    </aside>
</main>
@endsection
