@extends('layouts.app')

@section('title', 'Page expired - Saltmarsh Hotel test site')
@section('description', 'Your session timed out. Go back, reload the page and try again.')

@section('content')
<section class="section">
    <div class="container container--narrow error-page">
        <p class="eyebrow">Error 419</p>
        <h1 class="page-title">Page expired</h1>
        <p class="lead">Your session timed out. Go back, reload the page and try again.</p>
        <a class="btn btn--primary" href="{{ route('home') }}">Back to the home page</a>
    </div>
</section>
@endsection
