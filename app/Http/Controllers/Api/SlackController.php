<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelMessage;
use App\Models\ChannelReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Slack Web API look-alike for the simulated channel, so the mock-Slack MCP
 * server (and in v2 the real Slack MCP server) give the agent the same shapes.
 * Every response is {"ok": true, ...} or {"ok": false, "error": "..."}.
 */
class SlackController extends Controller
{
    /** conversations.history: top-level messages, newest first. */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'oldest' => ['nullable', 'string', 'max:32'],
        ]);
        $q = ChannelMessage::whereNull('thread_ts')->with('reactions')->orderByDesc('ts');
        if (! empty($data['oldest'])) {
            $q->where('ts', '>', $data['oldest']);
        }

        return response()->json([
            'ok' => true,
            'messages' => $q->limit($data['limit'] ?? 50)->get()->map->toSlack()->values(),
        ]);
    }

    /** conversations.replies: the parent message first, then its thread. */
    public function replies(Request $request): JsonResponse
    {
        $data = $request->validate(['ts' => ['required', 'string', 'max:32']]);
        $parent = ChannelMessage::where('ts', $data['ts'])->whereNull('thread_ts')->with('reactions')->first();
        if (! $parent) {
            return response()->json(['ok' => false, 'error' => 'thread_not_found'], 404);
        }
        $replies = ChannelMessage::where('thread_ts', $parent->ts)->with('reactions')->orderBy('ts')->get();

        return response()->json([
            'ok' => true,
            'messages' => collect([$parent])->concat($replies)->map->toSlack()->values(),
        ]);
    }

    /** chat.postMessage: always posts as Full Circle Agent. */
    public function postMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:1', 'max:4000'],
            'thread_ts' => ['nullable', 'string', 'max:32'],
        ]);
        if (! empty($data['thread_ts']) && ! ChannelMessage::where('ts', $data['thread_ts'])->whereNull('thread_ts')->exists()) {
            return response()->json(['ok' => false, 'error' => 'thread_not_found'], 404);
        }
        $m = ChannelMessage::create([
            'ts' => ChannelMessage::newTs(),
            'thread_ts' => $data['thread_ts'] ?? null,
            'user_id' => null,
            'author' => ChannelMessage::AGENT_NAME,
            'is_agent' => true,
            'text' => $data['text'],
        ]);

        return response()->json(['ok' => true, 'ts' => $m->ts, 'message' => $m->load('reactions')->toSlack()]);
    }

    /** reactions.add: adds the agent's reaction (idempotent). */
    public function reactionsAdd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'timestamp' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'in:'.implode(',', array_keys(ChannelReaction::EMOJI))],
        ]);
        $m = ChannelMessage::where('ts', $data['timestamp'])->first();
        if (! $m) {
            return response()->json(['ok' => false, 'error' => 'message_not_found'], 404);
        }
        // The agent may never approve its own work.
        if ($data['name'] === 'white_check_mark') {
            return response()->json(['ok' => false, 'error' => 'agent_cannot_approve'], 403);
        }
        $m->reactions()->firstOrCreate(
            ['name' => $data['name'], 'author' => ChannelMessage::AGENT_NAME],
            ['user_id' => null, 'is_agent' => true],
        );

        return response()->json(['ok' => true]);
    }
}
