@extends('layouts.app')

@section('title', 'Saltmarsh Hotel - rooms, restaurant and chat test site')
@section('description', 'Saltmarsh Hotel is a test hotel and restaurant website with a fixed-rules chatbot, a simulated Slack channel and a request board for the Full Circle Agent pilot.')
@section('path', '/')

@section('content')
<section class="hero">
    <img class="hero__img" src="{{ asset('images/hero-salt-marsh.webp') }}" width="2000" height="1359" alt="" aria-hidden="true" fetchpriority="high" decoding="async">
    <div class="hero__inner">
        <p class="eyebrow">Test site &middot; Full Circle Agent pilot</p>
        <h1 class="hero__title">Quiet rooms on the edge of the salt marsh</h1>
        <p class="hero__lead">Stay a night or two, eat at the Saltmarsh Grill, and ask our assistant anything. Change requests for this site go into the channel, and Full Circle Agent builds them.</p>
        <div class="hero__actions">
            <a class="btn btn--primary" href="{{ route('rooms') }}">See rooms and rates</a>
            <button type="button" class="btn btn--ghost" data-chat-open>Chat with us</button>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section__title">What you can do here</h2>
        <div class="grid grid--3">
            <article class="card">
                <h3>Rooms</h3>
                <p>Standard, Deluxe and Sea View rooms from R1450 a night, breakfast included.</p>
                <a class="text-link" href="{{ route('rooms') }}">Rooms and rates</a>
            </article>
            <article class="card">
                <h3>The Saltmarsh Grill</h3>
                <p>Open 07:00 to 22:00 every day. Line fish, lamb shank, risotto and malva pudding.</p>
                <a class="text-link" href="{{ route('restaurant') }}">Restaurant</a>
            </article>
            <article class="card">
                <h3>Chat assistant</h3>
                <p>Guests can browse; signed-in guests can book a room, reserve a table and see their bookings.</p>
                <button type="button" class="text-link link-button" data-chat-open>Open the chat</button>
            </article>
        </div>
    </div>
</section>

<section class="section section--alt">
    <div class="container split">
        <div>
            <h2 class="section__title">How the pilot works</h2>
            <ol class="steps">
                <li>A signed-in test user posts a change request in the <a href="{{ route('channel') }}">channel</a>.</li>
                <li>Full Circle Agent replies in the thread, changes the code and runs the tests.</li>
                <li>Progress shows on the public <a href="{{ route('board') }}">board</a>.</li>
                <li>A signed-in user approves in the thread, and only then does the change go live.</li>
                <li>Any test failures generate proposed tickets which, if approved, get created and attended to by Claude Code, followed by testing again. This repeats until no failures are observed.</li>
            </ol>
        </div>
        <div class="note">
            <h3>This is not a real hotel</h3>
            <p>Nothing on this site is booked, charged or emailed. The chatbot follows fixed rules from a Lucid flow diagram and makes no Artificial Intelligence calls.</p>
        </div>
    </div>
</section>
@endsection
