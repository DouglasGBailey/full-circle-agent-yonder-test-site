@extends('layouts.app')

@section('title', '#yonder-requests channel - Saltmarsh Hotel test site')
@section('description', 'The simulated Slack channel where pilot users post change requests for the Saltmarsh Hotel test site and approve the deploys that Full Circle Agent prepares.')
@section('path', '/channel')
@section('body_class', 'page-channel')

@push('scripts')
    <script src="{{ asset('js/channel.js') }}?v={{ filemtime(public_path('js/channel.js')) }}" defer></script>
@endpush

@section('content')
<section class="section section--tight">
    <div class="container channel" data-channel data-feed="{{ route('channel.feed') }}" data-post="{{ route('channel.post') }}" data-react="{{ route('channel.react') }}" data-signed-in="{{ auth()->check() ? '1' : '0' }}">
        <header class="channel__head">
            <div>
                <h1 class="page-title channel__title"><span class="channel__hash" aria-hidden="true">#</span>yonder-requests</h1>
                <p class="muted">Post a change request for this site. Full Circle Agent answers in the thread, builds and tests the change, then asks for approval. Times are SAST.</p>
            </div>
            <a class="btn btn--ghost" href="{{ route('board') }}">View the board</a>
        </header>

        <details class="help">
            <summary>How to request and approve a change</summary>
            <ol>
                <li>Write what you want changed, in one message. For example: <em>Add a "Spa" page with opening hours 09:00 to 18:00.</em></li>
                <li>Full Circle Agent replies in the thread within a couple of minutes. If it asks a question, answer in the thread.</li>
                <li>When it says the change is ready and the tests pass, reply <strong>approve</strong> in the thread, or add the <span aria-hidden="true">&#x2705;</span> reaction to its message.</li>
                <li>Only then does it deploy. It replies with the link when the change is live. Reply <strong>retry</strong> in the thread if a request failed.</li>
            </ol>
        </details>

        <div class="channel__feed" id="channel-feed" aria-live="polite" aria-busy="false" data-channel-feed>
            @include('channel._messages')
        </div>

        @auth
            <form class="composer" method="POST" action="{{ route('channel.post') }}" data-composer novalidate>
                @csrf
                <label for="composer-text" class="visually-hidden">New request</label>
                <textarea id="composer-text" name="text" rows="3" minlength="2" maxlength="2000" required placeholder="Message #yonder-requests"></textarea>
                <div class="composer__bar">
                    <span class="muted small">Posting as {{ auth()->user()->name }}. Enter sends, Shift+Enter adds a line.</span>
                    <button type="submit" class="btn btn--primary">Send</button>
                </div>
                <p class="composer__error" role="alert" hidden data-composer-error></p>
            </form>
        @else
            <div class="note channel__signin">
                <p><a href="{{ route('login') }}">Sign in</a> with your pilot test account to post requests, reply or approve. Anyone can read the channel.</p>
            </div>
        @endauth
    </div>
</section>
@endsection
