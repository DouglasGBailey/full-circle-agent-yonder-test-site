@extends('layouts.app')

@section('title', 'Rooms and rates - Saltmarsh Hotel test site')
@section('description', 'Standard, Deluxe, Sea View and Lake View rooms at the Saltmarsh Hotel test site, from R1450 a night with breakfast. Sign in and use the chat to book a test room.')
@section('path', '/rooms')

@section('content')
<section class="page-head">
    <div class="container">
        <h1 class="page-title">Rooms and rates</h1>
        <p class="lead">Every room has a king or twin bed, fast Wi-Fi and breakfast at the Saltmarsh Grill.</p>
    </div>
</section>
<section class="section">
    <div class="container grid grid--3">
        @foreach ([
            ['Standard', 'R1450', 'Garden-facing room with a king or twin bed and a walk-in shower.'],
            ['Deluxe', 'R1750', 'A larger room with a reading corner and a deep bath.'],
            ['Sea View', 'R2100', 'Top-floor room with a balcony over the marsh and the sea.'],
            ['Lake View', 'R2230', 'Top-floor room with a balcony over the lake.'],
        ] as [$name, $price, $text])
            <article class="card room">
                <h2 class="room__name">{{ $name }}</h2>
                <p class="room__price"><strong>{{ $price }}</strong> <span class="muted">a night</span></p>
                <p>{{ $text }}</p>
            </article>
        @endforeach
    </div>
    <div class="container">
        <p class="room-times">Check-in is from 14:00 and check-out is by 10:00.</p>
        <div class="note">
            <p>To book a test room, <a href="{{ route('login') }}">sign in</a> and open the chat. Try <strong>12 Dec, 2 nights</strong> (rooms free) or <strong>24 Dec, 2 nights</strong> (fully booked: 20 to 31 December).</p>
            <button type="button" class="btn btn--primary" data-chat-open>Open the chat</button>
        </div>
    </div>
</section>
@endsection
