@extends('layouts.app')

@section('title', $confirmed ? 'Name updated' : 'Confirm name change')

@section('body')
<main class="login-page">
    <section class="login-card">
        <a class="brand brand-login" href="{{ route('login') }}">
            <span class="brand-mark"><img class="brand-logo" src="{{ asset('images/pup-seal.png') }}" alt=""></span>
            <span class="brand-name">PUP - SRComLab</span>
        </a>
        <div class="login-intro">
            <span class="eyebrow">ADMINISTRATOR ACCOUNT</span>
            @if ($confirmed)
                <h1>Your name has been updated.</h1>
                <p>The administrator account is now listed as {{ $pendingName }}.</p>
            @else
                <h1>Confirm your name change.</h1>
                <p>Change the name for {{ $admin->email }} to <strong>{{ $pendingName }}</strong>?</p>
                <form method="POST" action="{{ request()->fullUrl() }}">
                    @csrf
                    <button class="button button-primary button-wide" type="submit">Confirm name change</button>
                </form>
            @endif
        </div>
    </section>
</main>
@endsection
