@extends('layouts.app')

@section('title', 'Contact - Saltmarsh Hotel test site')
@section('description', 'Contact the Saltmarsh Hotel test site. Reception hours are 07:00 to 21:00 SAST. This is a test site for the Full Circle Agent pilot, not a real hotel.')
@section('path', '/contact')

@section('content')
<section class="page-head">
    <div class="container">
        <h1 class="page-title">Contact</h1>
        <p class="lead">Reception is staffed from 07:00 to 21:00 SAST.</p>
    </div>
</section>
<section class="section">
    <div class="container grid grid--2">
        <article class="card">
            <h2>Ask in the chat</h2>
            <p>The quickest way to reach us is the chat. Signed-in guests can ask for reception from <em>My bookings</em>.</p>
            <button type="button" class="btn btn--primary" data-chat-open>Open the chat</button>
        </article>
        <article class="card">
            <h2>About this site</h2>
            <p>The Saltmarsh Hotel is fictional. This site exists to test Full Circle Agent for VML Yonder. To take part in the pilot, ask Douglas Bailey for a test account.</p>
            <p><a class="text-link" href="https://terminalvelocityai.tech/">terminalvelocityai.tech</a></p>
        </article>
    </div>
</section>
@endsection
