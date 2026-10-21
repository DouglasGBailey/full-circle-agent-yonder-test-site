/*
 * Saltmarsh Hotel test site: theme switch, mobile menu and the chat widget.
 * Plain JavaScript, no build step. The CSP allows only same-origin scripts.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'saltmarsh-theme';
    var root = document.documentElement;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
    window.Saltmarsh = { csrfToken: csrfToken };

    /* ---------- Theme switch (on = light mode; dark is the default) ---------- */
    function initTheme() {
        var sw = document.querySelector('.theme-switch');
        if (!sw) { return; }
        var meta = document.querySelector('meta[name="theme-color"]');

        function apply(theme) {
            root.setAttribute('data-theme', theme);
            sw.setAttribute('aria-checked', theme === 'light' ? 'true' : 'false');
            if (meta) { meta.setAttribute('content', theme === 'light' ? '#f6f4ee' : '#0b1418'); }
        }

        apply(root.getAttribute('data-theme') === 'light' ? 'light' : 'dark');
        sw.addEventListener('click', function () {
            var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            apply(next);
            try { localStorage.setItem(STORAGE_KEY, next); } catch (e) { /* private mode: still switches */ }
        });
    }

    /* ---------- Mobile menu ---------- */
    function initNav() {
        var toggle = document.querySelector('.nav-toggle');
        var nav = document.getElementById('site-nav');
        if (!toggle || !nav) { return; }

        function setOpen(open, returnFocus) {
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            if (!open && returnFocus) { toggle.focus(); }
        }
        function isOpen() { return nav.classList.contains('is-open'); }

        toggle.addEventListener('click', function () { setOpen(!isOpen(), false); });
        nav.addEventListener('click', function (e) {
            if (e.target.closest('a, button')) { setOpen(false, false); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) { setOpen(false, true); }
        });
        document.addEventListener('click', function (e) {
            if (isOpen() && !nav.contains(e.target) && !toggle.contains(e.target)) { setOpen(false, false); }
        });
        // Back / forward (including the bfcache restoring an open menu).
        window.addEventListener('popstate', function () { setOpen(false, false); });
        window.addEventListener('pageshow', function () { setOpen(false, false); });
        // Growing past the breakpoint leaves no stale open state behind.
        var wide = window.matchMedia('(min-width: 901px)');
        var onWide = function () { if (wide.matches) { setOpen(false, false); } };
        if (wide.addEventListener) { wide.addEventListener('change', onWide); }
    }

    /* ---------- Chat widget ---------- */
    function initChat() {
        var chat = document.querySelector('[data-chat]');
        if (!chat) { return; }
        var panel = chat.querySelector('.chat__panel');
        var launcher = chat.querySelector('.chat__launcher');
        var log = chat.querySelector('[data-chat-log]');
        var form = chat.querySelector('[data-chat-form]');
        var input = chat.querySelector('[data-chat-input]');
        var sendBtn = form.querySelector('button[type="submit"]');
        var endpoint = chat.getAttribute('data-endpoint');
        var started = false;
        var busy = false;

        function scrollDown() { log.scrollTop = log.scrollHeight; }

        function addBubble(text, who) {
            var p = document.createElement('p');
            p.className = 'chat__msg chat__msg--' + who;
            p.textContent = text;   // textContent: bot and visitor text are never parsed as HTML
            log.appendChild(p);
            scrollDown();
            return p;
        }

        function disableOldChoices() {
            log.querySelectorAll('.chat__choice').forEach(function (b) { b.disabled = true; });
        }

        function addButtons(labels) {
            if (!labels || !labels.length) { return; }
            var wrap = document.createElement('div');
            wrap.className = 'chat__buttons';
            labels.forEach(function (label) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'chat__choice';
                b.textContent = label;
                b.addEventListener('click', function () { send(label); });
                wrap.appendChild(b);
            });
            log.appendChild(wrap);
            scrollDown();
        }

        function setBusy(on) {
            busy = on;
            sendBtn.disabled = on;
            log.setAttribute('aria-busy', on ? 'true' : 'false');
        }

        function send(text, silent) {
            text = (text || '').trim();
            if (!text || busy) { return; }
            disableOldChoices();
            if (!silent) { addBubble(text, 'me'); }
            setBusy(true);
            var typing = addBubble('Typing...', 'bot');
            typing.className = 'chat__typing';

            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ message: text.slice(0, 200) })
            }).then(function (res) {
                if (res.status === 429) { throw new Error('You are sending messages very fast. Please wait a minute and try again.'); }
                if (res.status === 419) { throw new Error('This page has expired. Please reload it and try again.'); }
                if (!res.ok) { throw new Error('Sorry, the assistant could not answer just now. Please try again.'); }
                return res.json();
            }).then(function (data) {
                typing.remove();
                (data.messages || []).forEach(function (m) {
                    addBubble(m.text, 'bot');
                    addButtons(m.buttons);
                });
            }).catch(function (err) {
                typing.remove();
                addBubble(err.message || 'Something went wrong.', 'error');
            }).then(function () {
                setBusy(false);
            });
        }

        function open() {
            panel.hidden = false;
            launcher.setAttribute('aria-expanded', 'true');
            input.focus();
            if (!started) { started = true; send('hi', true); }
        }
        function close() {
            panel.hidden = true;
            launcher.setAttribute('aria-expanded', 'false');
            launcher.focus();
        }

        // Every [data-chat-open] opens it, including the hero's "Ask the assistant".
        document.querySelectorAll('[data-chat-open]').forEach(function (el) {
            el.addEventListener('click', function (e) { e.preventDefault(); open(); });
        });
        chat.querySelector('[data-chat-close]').addEventListener('click', close);
        panel.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.stopPropagation(); close(); }
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var text = input.value;
            if (!text.trim() || busy) { return; }
            input.value = '';
            send(text);
        });
    }

    function init() { initTheme(); initNav(); initChat(); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
