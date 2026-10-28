@extends('layouts.app')

@section('title', 'Forgot password')

@section('body')
<main class="login-page">
    <section class="login-card">
        <a class="brand brand-login" href="{{ route('login') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span>
            <span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="login-intro">
            <span class="eyebrow">ACCOUNT RECOVERY</span>
            <h1>Reset your<br>password.</h1>
            <p>Enter your school email address and we’ll send a password-reset link to verify it’s yours.</p>
        </div>
        @if (session('status'))
            <div class="flash flash-success" role="status">{{ session('status') }}</div>
        @endif
        <form class="stack-form" method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="email">School email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            @error('email') <p class="form-error">{{ $message }}</p> @enderror
            <button class="button button-primary button-wide" type="submit">Email password-reset link</button>
        </form>
        <p class="login-foot"><a href="{{ route('login') }}">Back to sign in</a></p>
    </section>
    <aside class="login-art" aria-label="Computer lab illustration">
        <div class="art-glow"></div>
        <div class="art-copy">
            <span class="eyebrow">SECURE ACCOUNT RECOVERY</span>
            <h2>Only you.<br>Back in.</h2>
            <p>A private link sent to your registered email lets you choose a new password.</p>
        </div>
        <span class="art-caption">CAMPUS LAB · PUP</span>
    </aside>
</main>
@endsection
