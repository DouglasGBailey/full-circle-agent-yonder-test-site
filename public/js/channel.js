/*
 * #yonder-requests: refresh the feed every 5 seconds and post, reply and react
 * without a full page reload. Without JavaScript the forms still work as
 * ordinary POSTs that redirect back to the message.
 */
(function () {
    'use strict';

    var POLL_MS = 5000;

    function init() {
        var channel = document.querySelector('[data-channel]');
        if (!channel) { return; }
        var feed = channel.querySelector('[data-channel-feed]');
        var feedUrl = channel.getAttribute('data-feed');
        var composer = channel.querySelector('[data-composer]');
        var lastHtml = null;
        var loading = false;

        function csrf() { return window.Saltmarsh ? window.Saltmarsh.csrfToken() : ''; }

        function nearBottom() {
            return window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 200;
        }

        // Do not swap the feed under someone who is typing a reply or choosing a reaction.
        function userIsBusy() {
            var active = document.activeElement;
            if (active && feed.contains(active) && active.matches('input, textarea')) { return true; }
            if (feed.querySelector('details.reaction-picker[open]')) { return true; }
            var typed = Array.prototype.some.call(feed.querySelectorAll('.reply-form input[name="text"]'), function (i) {
                return i.value.trim() !== '';
            });
            return typed;
        }

        function refresh(force) {
            if (loading || (!force && (document.hidden || userIsBusy()))) { return Promise.resolve(); }
            loading = true;
            feed.setAttribute('aria-busy', 'true');
            var stick = nearBottom();
            return fetch(feedUrl, { credentials: 'same-origin', headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { if (!res.ok) { throw new Error(res.status); } return res.text(); })
                .then(function (html) {
                    if (html !== lastHtml) {
                        lastHtml = html;
                        feed.innerHTML = html;   // server-rendered Blade; every message text is escaped there
                        if (stick || force) { window.scrollTo(0, document.documentElement.scrollHeight); }
                    }
                })
                .catch(function () { /* offline or rate limited: try again on the next tick */ })
                .then(function () { loading = false; feed.setAttribute('aria-busy', 'false'); });
        }

        function postForm(form) {
            var body = new FormData(form);
            return fetch(form.getAttribute('action'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).then(function (res) {
                if (res.ok) { return res.json(); }
                if (res.status === 422) {
                    return res.json().then(function (d) {
                        var errs = d.errors ? Object.values(d.errors).flat() : [];
                        throw new Error(errs[0] || d.message || 'Please check the message and try again.');
                    });
                }
                if (res.status === 429) { throw new Error('You are posting very fast. Please wait a minute and try again.'); }
                if (res.status === 419) { throw new Error('This page has expired. Please reload it and sign in again.'); }
                if (res.status === 401) { throw new Error('Please sign in again.'); }
                throw new Error('Could not send. Please try again.');
            });
        }

        // One delegated handler covers forms that arrive with each refresh.
        feed.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form.matches('[data-react-form], [data-reply-form]')) { return; }
            e.preventDefault();
            var btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; }
            postForm(form).then(function () {
                if (form.matches('[data-reply-form]')) { form.reset(); }
                if (document.activeElement) { document.activeElement.blur(); }
                return refresh(true);
            }).catch(function (err) {
                if (btn) { btn.disabled = false; }
                var input = form.querySelector('input[name="text"]');
                if (input) { input.setCustomValidity(err.message); input.reportValidity(); input.addEventListener('input', function () { input.setCustomValidity(''); }, { once: true }); }
            });
        });

        // Close an open reaction picker on Escape or a click outside it.
        document.addEventListener('click', function (e) {
            feed.querySelectorAll('details.reaction-picker[open]').forEach(function (d) {
                if (!d.contains(e.target)) { d.removeAttribute('open'); }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') { return; }
            feed.querySelectorAll('details.reaction-picker[open]').forEach(function (d) {
                d.removeAttribute('open');
                var s = d.querySelector('summary');
                if (s) { s.focus(); }
            });
        });

        if (composer) {
            var textarea = composer.querySelector('textarea');
            var errorBox = composer.querySelector('[data-composer-error]');
            var sendBtn = composer.querySelector('button[type="submit"]');

            textarea.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                    e.preventDefault();
                    composer.requestSubmit ? composer.requestSubmit() : composer.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
            composer.addEventListener('submit', function (e) {
                e.preventDefault();
                errorBox.hidden = true;
                if (textarea.value.trim().length < 2) {
                    errorBox.textContent = 'Please write at least two characters.';
                    errorBox.hidden = false;
                    return;
                }
                sendBtn.disabled = true;
                postForm(composer).then(function () {
                    textarea.value = '';
                    return refresh(true);
                }).catch(function (err) {
                    errorBox.textContent = err.message;
                    errorBox.hidden = false;
                }).then(function () { sendBtn.disabled = false; });
            });
        }

        setInterval(function () { refresh(false); }, POLL_MS);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(false); } });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
