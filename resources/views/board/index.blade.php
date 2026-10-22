@extends('layouts.app')

@section('title', 'Request board - Saltmarsh Hotel test site')
@section('description', 'The public Full Circle Agent request board: every change request from the channel with its status, branch, test result, approver and time it went live.')
@section('path', '/board')

@section('content')
<section class="page-head">
    <div class="container">
        <h1 class="page-title">Request board</h1>
        <p class="lead">One card per change request from the <a href="{{ route('channel') }}">channel</a>. Only Full Circle Agent updates this board. Times are SAST.</p>
    </div>
</section>
<section class="section section--tight">
    <div class="container">
        <ul class="status-counts" aria-label="Requests by status">
            @foreach ($statuses as $status)
                <li class="status-count status--{{ \Illuminate\Support\Str::slug($status) }}">
                    <span class="status-count__n">{{ $cards->where('status', $status)->count() }}</span>
                    <span class="status-count__label">{{ $status }}</span>
                </li>
            @endforeach
        </ul>

        @forelse ($cards as $card)
            <article class="board-card" id="card-{{ $card->id }}">
                <header class="board-card__head">
                    <h2 class="board-card__title">#{{ $card->id }} {{ $card->title }}</h2>
                    <span class="badge status--{{ \Illuminate\Support\Str::slug($card->status) }}">{{ $card->status }}</span>
                </header>
                <blockquote class="board-card__request">{{ $card->request_text }}</blockquote>
                <dl class="board-card__meta">
                    <div><dt>Requested by</dt><dd>{{ $card->requested_by }}</dd></div>
                    <div><dt>Requested</dt><dd>{{ $card->created_at?->timezone('Africa/Johannesburg')->format('j M Y H:i') }} SAST</dd></div>
                    @if ($card->branch)<div><dt>Branch</dt><dd><code>{{ $card->branch }}</code></dd></div>@endif
                    @if ($card->commit_url)<div><dt>Commit</dt><dd><a href="{{ $card->commit_url }}" rel="noopener nofollow">view on GitHub</a></dd></div>@endif
                    @if ($card->test_result)<div><dt>Tests</dt><dd>{{ $card->test_result }}</dd></div>@endif
                    @if ($card->approved_by)<div><dt>Approved by</dt><dd>{{ $card->approved_by }}</dd></div>@endif
                    @if ($card->deployed_at)<div><dt>Live</dt><dd>{{ $card->deployed_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }} SAST ({{ (int) $card->created_at->diffInMinutes($card->deployed_at) }} min)</dd></div>@endif
                    @if ($card->attempts > 1)<div><dt>Attempts</dt><dd>{{ $card->attempts }}</dd></div>@endif
                </dl>
                @if ($card->notes)<p class="board-card__notes">{{ $card->notes }}</p>@endif
                @if ($card->tickets->isNotEmpty())
                    <div class="board-card__tickets">
                        <h3 class="board-card__tickets-title">Tickets from failed tests</h3>
                        <ul class="tickets">
                            @foreach ($card->tickets as $ticket)
                                <li class="ticket">
                                    <span class="ticket__round">Round {{ $ticket->round }}</span>
                                    <span class="ticket__title">{{ $ticket->title }}</span>
                                    <span class="badge badge--small status--{{ \Illuminate\Support\Str::slug($ticket->status) }}">{{ $ticket->status }}</span>
                                    @if ($ticket->approved_by)<span class="ticket__by small">approved by {{ $ticket->approved_by }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($card->request_ts)<p class="small"><a href="{{ route('channel') }}#m-{{ str_replace('.', '-', $card->request_ts) }}">Open the thread</a></p>@endif
            </article>
        @empty
            <div class="empty">
                <h2>No requests yet</h2>
                <p>When someone posts a change request in the channel, Full Circle Agent adds a card here within a couple of minutes.</p>
                <a class="btn btn--primary" href="{{ route('channel') }}">Go to the channel</a>
            </div>
        @endforelse
    </div>
</section>
@endsection
