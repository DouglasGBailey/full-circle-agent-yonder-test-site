@extends('layouts.app')

@section('title', 'The Saltmarsh Grill - restaurant test site')
@section('description', 'The Saltmarsh Grill is open 07:00 to 22:00 with grilled line fish, slow-cooked lamb shank, mushroom risotto and malva pudding. Reserve a test table in the chat.')
@section('path', '/restaurant')

@section('content')
<section class="page-head">
    <div class="container">
        <h1 class="page-title">The Saltmarsh Grill</h1>
        <p class="lead">Open 07:00 to 22:00, every day. Breakfast for hotel guests, lunch and dinner for everyone.</p>
    </div>
</section>
<section class="section">
    <div class="container split">
        <div>
            <h2 class="section__title">Tonight's menu</h2>
            <ul class="menu-list">
                <li><span>Grilled line fish</span><span class="muted">lemon butter, greens</span></li>
                <li><span>Slow-cooked lamb shank</span><span class="muted">mash, rosemary jus</span></li>
                <li><span>Mushroom risotto</span><span class="muted">parmesan, thyme</span></li>
                <li><span>Cape Malay chicken curry</span><span class="muted">yellow rice</span></li>
                <li><span>Malva pudding</span><span class="muted">custard</span></li>
            </ul>
        </div>
        <div class="note">
            <h3>Reserve a table</h3>
            <p>Signed-in guests can reserve a table in the chat. Give the day, time and number of people, for example <strong>Fri 19:30, 4 people</strong>.</p>
            <button type="button" class="btn btn--primary" data-chat-open>Open the chat</button>
        </div>
    </div>
</section>
@endsection
