@extends('layouts.app')

@section('title', 'Something went wrong - Saltmarsh Hotel test site')
@section('description', 'The site hit an error. It has been logged. Please try again in a moment.')

@section('content')
<section class="section">
    <div class="container container--narrow error-page">
        <p class="eyebrow">Error 500</p>
        <h1 class="page-title">Something went wrong</h1>
        <p class="lead">The site hit an error. It has been logged. Please try again in a moment.</p>
        <a class="btn btn--primary" href="{{ route('home') }}">Back to the home page</a>
    </div>
</section>
@endsection
