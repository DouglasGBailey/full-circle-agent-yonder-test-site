@extends('layouts.app')

@section('title', 'Slow down a little - Saltmarsh Hotel test site')
@section('description', 'Too many requests from you in a short time. Please wait a minute and try again.')

@section('content')
<section class="section">
    <div class="container container--narrow error-page">
        <p class="eyebrow">Error 429</p>
        <h1 class="page-title">Slow down a little</h1>
        <p class="lead">Too many requests from you in a short time. Please wait a minute and try again.</p>
        <a class="btn btn--primary" href="{{ route('home') }}">Back to the home page</a>
    </div>
</section>
@endsection
