<?php

namespace App\Http\Controllers;

use App\Models\ChannelMessage;
use App\Models\ChannelReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The simulated Slack channel #yonder-requests. Anyone may read it; only
 * signed-in test accounts may post, reply or react.
 */
class ChannelController extends Controller
{
    public function index(): View
    {
        return view('channel.index', ['messages' => $this->topLevel()]);
    }

    /** Partial HTML of the message list, polled by the page every few seconds. */
    public function feed(): View
    {
        return view('channel._messages', ['messages' => $this->topLevel()]);
    }

    public function post(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:2', 'max:2000'],
            'thread_ts' => ['nullable', 'string', 'max:32', Rule::exists('channel_messages', 'ts')->whereNull('thread_ts')],
        ]);

        $message = ChannelMessage::create([
            'ts' => ChannelMessage::newTs(),
            'thread_ts' => $data['thread_ts'] ?? null,
            'user_id' => $request->user()->id,
            'author' => $request->user()->name,
            'is_agent' => false,
            'text' => trim($data['text']),
        ]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'ts' => $message->ts])
            : redirect()->to(route('channel').'#m-'.str_replace('.', '-', $message->thread_ts ?? $message->ts));
    }

    public function react(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'ts' => ['required', 'string', 'max:32', 'exists:channel_messages,ts'],
            'name' => ['required', Rule::in(ChannelReaction::ALLOWED)],
        ]);
        $message = ChannelMessage::where('ts', $data['ts'])->firstOrFail();
        $user = $request->user();

        // Clicking a reaction you already added removes it, as in Slack.
        $existing = $message->reactions()->where('name', $data['name'])->where('author', $user->name)->where('is_agent', false)->first();
        if ($existing) {
            $existing->delete();
        } else {
            $message->reactions()->create(['name' => $data['name'], 'user_id' => $user->id, 'author' => $user->name, 'is_agent' => false]);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->to(route('channel').'#m-'.str_replace('.', '-', $message->thread_ts ?? $message->ts));
    }

    private function topLevel()
    {
        return ChannelMessage::whereNull('thread_ts')
            ->with(['reactions', 'replies.reactions'])
            ->orderByDesc('ts')->limit(50)->get()->reverse()->values();
    }
}
