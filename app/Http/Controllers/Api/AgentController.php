<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardCard;
use App\Models\ChannelMessage;
use App\Services\AgentQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The agent's work queue, the approval gate and the board cards. */
class AgentController extends Controller
{
    /** Which status may follow which. Deploying needs an approval as well. */
    private const NEXT = [
        'New' => ['In progress', 'Needs info', 'Failed'],
        'In progress' => ['Needs info', 'Waiting approval', 'Failed'],
        'Needs info' => ['In progress', 'Failed'],
        'Waiting approval' => ['In progress', 'Deploying', 'Failed'],
        'Deploying' => ['Deployed', 'Failed'],
        'Deployed' => [],
        'Failed' => ['In progress'],
    ];

    public function __construct(private AgentQueue $queue) {}

    public function pending(): JsonResponse
    {
        return response()->json(['ok' => true, 'items' => $this->queue->pending()]);
    }

    public function approval(Request $request): JsonResponse
    {
        $data = $request->validate(['request_ts' => ['required', 'string', 'max:32']]);
        $card = BoardCard::where('request_ts', $data['request_ts'])->first();
        if (! $card) {
            return response()->json(['ok' => false, 'error' => 'card_not_found'], 404);
        }

        return response()->json(['ok' => true, 'status' => $card->status] + $this->queue->approval($card));
    }

    public function cards(): JsonResponse
    {
        return response()->json(['ok' => true, 'cards' => BoardCard::orderByDesc('id')->limit(200)->get()->map->toApi()->values()]);
    }

    public function createCard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_ts' => ['required', 'string', 'max:32', 'unique:board_cards,request_ts'],
            'title' => ['required', 'string', 'min:3', 'max:160'],
        ]);
        $msg = ChannelMessage::where('ts', $data['request_ts'])->whereNull('thread_ts')->where('is_agent', false)->first();
        if (! $msg) {
            return response()->json(['ok' => false, 'error' => 'request_not_found'], 404);
        }
        $card = BoardCard::create([
            'request_ts' => $msg->ts,
            'title' => $data['title'],
            'request_text' => $msg->text,
            'requested_by' => $msg->author,
            'status' => 'New',
            'status_changed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'card' => $card->toApi()], 201);
    }

    public function updateCard(Request $request, BoardCard $card): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(BoardCard::STATUSES)],
            'title' => ['nullable', 'string', 'min:3', 'max:160'],
            'branch' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'commit_url' => ['nullable', 'url:https', 'max:300', 'starts_with:https://github.com/'],
            'test_result' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $status = $data['status'] ?? null;
        unset($data['status']);
        $data = array_filter($data, fn ($v) => $v !== null);

        if ($status !== null && $status !== $card->status) {
            if (! in_array($status, self::NEXT[$card->status], true)) {
                return response()->json(['ok' => false, 'error' => "bad_transition: {$card->status} -> {$status}"], 409);
            }
            if ($status === 'Deploying') {
                // Server-side copy of the approval gate.
                $approval = $this->queue->approval($card);
                if (! $approval['approved']) {
                    return response()->json(['ok' => false, 'error' => 'not_approved'], 409);
                }
                $data['approved_by'] = $approval['approved_by'];
            }
            if ($status === 'Waiting approval') {
                $data['waiting_since'] = now();
                $data['approved_by'] = null;
            }
            if ($status === 'In progress' && in_array($card->status, ['Failed', 'Needs info', 'Waiting approval'], true)) {
                $data['attempts'] = $card->attempts + ($card->status === 'Failed' ? 1 : 0);
            }
            if ($status === 'Deployed') {
                $data['deployed_at'] = now();
            }
            $data['status'] = $status;
            $data['status_changed_at'] = now();
        }

        $card->fill($data)->save();

        return response()->json(['ok' => true, 'card' => $card->fresh()->toApi()]);
    }
}
