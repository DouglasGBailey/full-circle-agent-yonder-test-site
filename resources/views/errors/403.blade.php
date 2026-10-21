@extends('layouts.app')

@section('title', 'Not allowed - Saltmarsh Hotel test site')
@section('description', 'You do not have permission to do that.')

@section('content')
<section class="section">
    <div class="container container--narrow error-page">
        <p class="eyebrow">Error 403</p>
        <h1 class="page-title">Not allowed</h1>
        <p class="lead">You do not have permission to do that.</p>
        <a class="btn btn--primary" href="{{ route('home') }}">Back to the home page</a>
    </div>
</section>
@endsection
