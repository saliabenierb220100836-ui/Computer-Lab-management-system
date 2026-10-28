@extends('layouts.app')

@section('title', 'Image upload too large')

@section('body')
<main class="login-page">
    <section class="login-card">
        <div class="login-intro">
            <span class="eyebrow">IMAGE UPLOAD</span>
            <h1>That file is too large.</h1>
            <p>Your certificate image could not be uploaded. Choose an image no larger than 10 MB, then try again.</p>
        </div>
        <a class="button button-primary button-wide" href="{{ $returnUrl }}">Back to form</a>
    </section>
</main>
@endsection
