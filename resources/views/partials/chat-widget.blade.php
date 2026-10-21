{{-- Floating chat widget. The bot is fixed rules (resources/flows), no Artificial Intelligence. --}}
<div class="chat" data-chat data-endpoint="{{ route('chat') }}">
    <button type="button" class="chat__launcher" aria-expanded="false" aria-controls="chat-panel" data-chat-open>
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
        <span>Chat with us</span>
    </button>
    <section id="chat-panel" class="chat__panel" role="dialog" aria-modal="false" aria-labelledby="chat-title" hidden>
        <header class="chat__head">
            <div>
                <h2 id="chat-title" class="chat__title">Saltmarsh assistant</h2>
                <p class="chat__sub">
                    @auth Signed in as {{ auth()->user()->name }} @else Guest - <a href="{{ route('login') }}">sign in</a> to book @endauth
                </p>
            </div>
            <button type="button" class="chat__close" aria-label="Close chat" data-chat-close>&times;</button>
        </header>
        <div class="chat__log" role="log" aria-live="polite" aria-relevant="additions" data-chat-log></div>
        <form class="chat__form" data-chat-form autocomplete="off">
            <label class="visually-hidden" for="chat-input">Message</label>
            <input id="chat-input" name="message" type="text" maxlength="200" placeholder="Type a message or tap a button" required data-chat-input>
            <button type="submit" class="btn btn--primary">Send</button>
        </form>
    </section>
</div>
