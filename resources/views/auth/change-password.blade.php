@extends('layouts.app')

@section('title', 'Change password')

@section('body')
<main class="login-page">
    <section class="login-card">
        <a class="brand brand-login" href="{{ route('dashboard') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span>
            <span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="login-intro">
            <span class="eyebrow">ACCOUNT SECURITY</span>
            <h1>Change your<br>password.</h1>
            <p>Your verified school email is required. Confirm your current password before choosing a new one.</p>
        </div>
        @if (session('success'))
            <div class="flash flash-success" role="status">{{ session('success') }}</div>
        @endif
        <form class="stack-form" method="POST" action="{{ route('password.change.update') }}">
            @csrf
            @method('PUT')
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
            @error('current_password') <p class="form-error">{{ $message }}</p> @enderror
            <label for="password">New password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="16" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,16}" title="12–16 characters with uppercase, lowercase, a number, and a special character" required>
            <p class="field-hint">12–16 characters; include uppercase and lowercase letters, a number, and a special character.</p>
            @error('password') <p class="form-error">{{ $message }}</p> @enderror
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="16" required>
            <button class="button button-primary button-wide" type="submit">Change password</button>
        </form>
        <p class="login-foot"><a href="{{ route('dashboard') }}">Back to dashboard</a></p>
    </section>
    <aside class="login-art" aria-label="Computer lab illustration">
        <div class="art-glow"></div>
        <div class="art-copy">
            <span class="eyebrow">ACCOUNT SECURITY</span>
            <h2>Your account.<br>Your control.</h2>
            <p>Keep your account secure with a password only you know.</p>
        </div>
        <span class="art-caption">CAMPUS LAB · PUP</span>
    </aside>
</main>
@endsection
