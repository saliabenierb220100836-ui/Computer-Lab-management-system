@extends('layouts.app')

@section('title', 'Choose a new password')

@section('body')
<main class="login-page">
    <section class="login-card">
        <a class="brand brand-login" href="{{ route('login') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span>
            <span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="login-intro">
            <span class="eyebrow">EMAIL VERIFIED</span>
            <h1>Choose a new<br>password.</h1>
            <p>Set a new password for your account.</p>
        </div>
        <form class="stack-form" method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">School email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus>
            @error('email') <p class="form-error">{{ $message }}</p> @enderror
            <label for="password">New password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="16" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,16}" title="12–16 characters with uppercase, lowercase, a number, and a special character" required>
            <p class="field-hint">12–16 characters; include uppercase and lowercase letters, a number, and a special character.</p>
            @error('password') <p class="form-error">{{ $message }}</p> @enderror
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="16" required>
            <button class="button button-primary button-wide" type="submit">Reset password</button>
        </form>
        <p class="login-foot"><a href="{{ route('login') }}">Back to sign in</a></p>
    </section>
    <aside class="login-art" aria-label="Computer lab illustration">
        <div class="art-glow"></div>
        <div class="art-copy">
            <span class="eyebrow">SECURE ACCOUNT RECOVERY</span>
            <h2>Fresh start.<br>Same account.</h2>
            <p>Your new password replaces the old one immediately.</p>
        </div>
        <span class="art-caption">CAMPUS LAB · PUP</span>
    </aside>
</main>
@endsection
