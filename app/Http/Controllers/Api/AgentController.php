<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardCard;
use App\Models\BoardTicket;
use App\Models\ChannelMessage;
use App\Services\AgentQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The agent's work queue, the approval gate and the board cards. */
class AgentController extends Controller
{
    /**
     * Which status may follow which. Deploying needs an approval, and leaving
     * Tickets proposed for In progress needs the tickets to be approved.
     */
    private const NEXT = [
        'New' => ['In progress', 'Needs info', 'Failed'],
        'In progress' => ['Needs info', 'Tickets proposed', 'Waiting approval', 'Failed'],
        'Needs info' => ['In progress', 'Failed'],
        'Tickets proposed' => ['In progress', 'Failed'],
        'Waiting approval' => ['In progress', 'Deploying', 'Failed'],
        'Deploying' => ['Deployed', 'Tickets proposed', 'Failed'],
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
        return response()->json(['ok' => true, 'cards' => BoardCard::with('tickets')->orderByDesc('id')->limit(200)->get()->map->toApi()->values()]);
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
            if ($status === 'Tickets proposed' && ! $card->tickets()->where('status', 'Proposed')->exists()) {
                return response()->json(['ok' => false, 'error' => 'no_proposed_tickets'], 409);
            }
            $ticketsApprovedBy = null;
            if ($card->status === 'Tickets proposed' && $status === 'In progress') {
                // Server-side copy of the tickets gate: a person must say "approve" first.
                $approval = $this->queue->ticketsApproval($card);
                if (! $approval['approved']) {
                    return response()->json(['ok' => false, 'error' => 'not_approved'], 409);
                }
                $ticketsApprovedBy = $approval['approved_by'];
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

        DB::transaction(function () use ($card, $data, $status, &$ticketsApprovedBy) {
            $from = $card->status;
            $card->fill($data)->save();
            if ($from === 'Tickets proposed' && $status === 'In progress') {
                $card->tickets()->where('status', 'Proposed')->update(['status' => 'Open', 'approved_by' => $ticketsApprovedBy]);
            } elseif ($from === 'Tickets proposed' && $status === 'Failed') {
                $card->tickets()->where('status', 'Proposed')->update(['status' => 'Dropped']);
            }
        });

        return response()->json(['ok' => true, 'card' => $card->fresh()->toApi()]);
    }

    /**
     * Turns the latest test failures into Proposed tickets for a new round, and
     * closes the round before it: an Open ticket whose title fails again is
     * "Still failing", the rest are "Fixed". An empty list only closes the round
     * (the tests passed). The card must be In progress or Deploying.
     */
    public function proposeTickets(Request $request, BoardCard $card): JsonResponse
    {
        $data = $request->validate([
            'tickets' => ['present', 'array', 'max:10'],
            'tickets.*.title' => ['required', 'string', 'min:3', 'max:200'],
            'tickets.*.detail' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! in_array($card->status, ['In progress', 'Deploying'], true)) {
            return response()->json(['ok' => false, 'error' => "bad_status: {$card->status}"], 409);
        }
        $new = collect($data['tickets']);
        $round = (int) $card->tickets()->max('round') + 1;
        if ($new->isNotEmpty() && $round > BoardCard::MAX_TICKET_ROUNDS) {
            return response()->json(['ok' => false, 'error' => 'too_many_rounds'], 409);
        }

        DB::transaction(function () use ($card, $new, $round) {
            $titles = $new->pluck('title')->all();
            foreach ($card->tickets()->where('status', 'Open')->get() as $open) {
                $open->update(['status' => in_array($open->title, $titles, true) ? 'Still failing' : 'Fixed']);
            }
            $card->tickets()->where('status', 'Proposed')->update(['status' => 'Dropped']);
            foreach ($new as $t) {
                BoardTicket::create([
                    'board_card_id' => $card->id, 'round' => $round, 'title' => $t['title'],
                    'detail' => $t['detail'] ?? null, 'status' => 'Proposed',
                ]);
            }
        });

        return response()->json(['ok' => true, 'card' => $card->fresh()->toApi()], $new->isNotEmpty() ? 201 : 200);
    }
}
