@php
    $emoji = \App\Models\ChannelReaction::EMOJI;
    $allowed = \App\Models\ChannelReaction::ALLOWED;
    $me = auth()->user();
    $when = fn ($ts) => \Illuminate\Support\Carbon::createFromTimestamp((int) $ts, 'Africa/Johannesburg');
    $anchor = fn ($ts) => 'm-'.str_replace('.', '-', $ts);
@endphp
@forelse ($messages as $message)
    <article class="msg-thread" id="{{ $anchor($message->ts) }}" data-ts="{{ $message->ts }}">
        @foreach (collect([$message])->concat($message->replies) as $m)
            @php $isReply = $m->thread_ts !== null; @endphp
            <div class="msg {{ $isReply ? 'msg--reply' : '' }} {{ $m->is_agent ? 'msg--agent' : '' }}" @if ($isReply) id="{{ $anchor($m->ts) }}" @endif>
                <div class="msg__avatar" aria-hidden="true">{{ $m->is_agent ? 'FC' : mb_strtoupper(mb_substr($m->author, 0, 1)) }}</div>
                <div class="msg__body">
                    <p class="msg__meta">
                        <strong class="msg__author">{{ $m->author }}</strong>
                        @if ($m->is_agent)<span class="tag">agent</span>@endif
                        <time datetime="{{ $when($m->ts)->toIso8601String() }}" class="muted small">{{ $when($m->ts)->format('j M H:i') }} SAST</time>
                    </p>
                    <div class="msg__text">{!! nl2br(e($m->text)) !!}</div>
                    @php $groups = $m->reactions->groupBy('name'); @endphp
                    @if ($groups->isNotEmpty() || $me)
                        <div class="reactions">
                            @foreach ($groups as $name => $list)
                                @php $mine = $me && $list->contains(fn ($r) => ! $r->is_agent && $r->author === $me->name); @endphp
                                @if ($me && in_array($name, $allowed, true))
                                    <form method="POST" action="{{ route('channel.react') }}" class="reaction-form" data-react-form>
                                        @csrf
                                        <input type="hidden" name="ts" value="{{ $m->ts }}">
                                        <input type="hidden" name="name" value="{{ $name }}">
                                        <button type="submit" class="reaction {{ $mine ? 'reaction--mine' : '' }}" aria-pressed="{{ $mine ? 'true' : 'false' }}" title="{{ $list->pluck('author')->join(', ') }}">
                                            <span aria-hidden="true">{{ $emoji[$name] ?? ':'.$name.':' }}</span> {{ $list->count() }}<span class="visually-hidden"> {{ str_replace('_', ' ', $name) }}</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="reaction" title="{{ $list->pluck('author')->join(', ') }}"><span aria-hidden="true">{{ $emoji[$name] ?? ':'.$name.':' }}</span> {{ $list->count() }}<span class="visually-hidden"> {{ str_replace('_', ' ', $name) }}</span></span>
                                @endif
                            @endforeach
                            @if ($me)
                                <details class="reaction-picker">
                                    <summary class="reaction reaction--add" aria-label="Add a reaction">+</summary>
                                    <div class="reaction-picker__list">
                                        @foreach ($allowed as $name)
                                            <form method="POST" action="{{ route('channel.react') }}" class="reaction-form" data-react-form>
                                                @csrf
                                                <input type="hidden" name="ts" value="{{ $m->ts }}">
                                                <input type="hidden" name="name" value="{{ $name }}">
                                                <button type="submit" class="reaction" aria-label="React {{ str_replace('_', ' ', $name) }}"><span aria-hidden="true">{{ $emoji[$name] }}</span></button>
                                            </form>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
        @if ($me)
            <form method="POST" action="{{ route('channel.post') }}" class="reply-form" data-reply-form>
                @csrf
                <input type="hidden" name="thread_ts" value="{{ $message->ts }}">
                <label for="reply-{{ $anchor($message->ts) }}" class="visually-hidden">Reply in this thread</label>
                <input id="reply-{{ $anchor($message->ts) }}" name="text" type="text" minlength="2" maxlength="2000" required placeholder="Reply in thread ({{ $message->replies->count() }} {{ \Illuminate\Support\Str::plural('reply', $message->replies->count()) }})" autocomplete="off">
                <button type="submit" class="btn btn--small">Reply</button>
            </form>
        @elseif ($message->replies->count())
            <p class="muted small msg-thread__count">{{ $message->replies->count() }} {{ \Illuminate\Support\Str::plural('reply', $message->replies->count()) }}</p>
        @endif
    </article>
@empty
    <div class="empty">
        <h2>No requests yet</h2>
        <p>Be the first: sign in and describe one change you would like on this site.</p>
    </div>
@endforelse
