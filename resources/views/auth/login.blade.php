@extends('layouts.app')

@section('title', 'Sign in')

@section('body')
<main class="login-page">
    <section class="login-card">
        <a class="brand brand-login" href="{{ route('login') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span>
            <span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="login-intro">
            <span class="eyebrow">COMPUTER LAB PORTAL</span>
            <h1>Your lab,<br>in good hands.</h1>
            <p>Sign in to find your station, manage a class, or keep the lab running smoothly.</p>
        </div>
        <form class="stack-form" method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="identity">Email or campus ID</label>
            <input id="identity" name="identity" value="{{ old('identity') }}" autocomplete="username" placeholder="you@campus.edu" required autofocus>
            @error('identity') <p class="form-error">{{ $message }}</p> @enderror
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" placeholder="12–16 character password" minlength="12" maxlength="16" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,16}" title="12–16 characters with uppercase, lowercase, a number, and a special character" required>
            @error('password') <p class="form-error">{{ $message }}</p> @enderror
            <label class="remember-row"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button class="button button-primary button-wide" type="submit">Sign in</button>
        </form>
        <p class="login-foot"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
        <p class="login-foot">New student? <a href="{{ route('register') }}">Create an account</a></p>
        <p class="login-foot">Faculty member? <a href="{{ route('register.faculty') }}">Register with your PUP email</a></p>
    </section>
    <aside class="login-art" aria-label="Computer lab illustration">
        <div class="art-glow"></div>
        <div class="art-copy">
            <span class="eyebrow">BUILT FOR THE WAY YOU LEARN</span>
            <h2>Every seat.<br>In sync.</h2>
            <p>One clear view of your lab, your classes, and the people who make it work.</p>
        </div>
        <span class="art-caption">CAMPUS LAB · LIVE STATUS</span>
    </aside>
</main>
@endsection
