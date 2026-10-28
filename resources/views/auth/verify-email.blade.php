@extends('layouts.app')

@section('title', 'Verify school email')

@section('body')
<main class="login-page">
    <section class="login-card">
        <a class="brand brand-login" href="{{ route('login') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span>
            <span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="login-intro">
            <span class="eyebrow">ONE QUICK CHECK</span>
            <h1>Check your<br>school inbox.</h1>
            <p>We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Verify your official school email to open your lab dashboard.</p>
        </div>
        @if (session('success'))
            <div class="flash flash-success" role="status">{{ session('success') }}</div>
        @endif
        <form class="stack-form" method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="button button-primary button-wide" type="submit">Send a fresh verification link</button>
        </form>
        <form class="verification-logout" method="POST" action="{{ route('logout') }}">@csrf<button class="text-link" type="submit">Sign out</button></form>
        <p class="login-foot">Check your spam folder if the email isn’t there.</p>
    </section>
    <aside class="login-art" aria-label="Computer lab illustration">
        <div class="art-glow"></div>
        <div class="art-copy">
            <span class="eyebrow">SECURE BY DESIGN</span>
            <h2>Your account.<br>Your campus.</h2>
            <p>Email verification helps us keep student registration tied to an official PUP school mailbox.</p>
        </div>
        <span class="art-caption">CAMPUS LAB · PUP</span>
    </aside>
</main>
@endsection
