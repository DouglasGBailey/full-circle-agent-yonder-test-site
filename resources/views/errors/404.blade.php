@extends('layouts.app')

@section('title', 'Page not found - Saltmarsh Hotel test site')
@section('description', 'That page does not exist. It may have moved, or the link may be wrong.')

@section('content')
<section class="section">
    <div class="container container--narrow error-page">
        <p class="eyebrow">Error 404</p>
        <h1 class="page-title">Page not found</h1>
        <p class="lead">That page does not exist. It may have moved, or the link may be wrong.</p>
        <a class="btn btn--primary" href="{{ route('home') }}">Back to the home page</a>
    </div>
</section>
@endsection
