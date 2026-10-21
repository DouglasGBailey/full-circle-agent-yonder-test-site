<?php

namespace App\Services;

use App\Models\BoardCard;
use App\Models\ChannelMessage;
use Illuminate\Support\Carbon;

/**
 * Works out what Full Circle Agent has to do next. The MacBook watcher calls
 * this every minute (for free) and only starts Claude Code when the list is
 * not empty.
 *
 * Kinds of work:
 *   new_request - a person's top-level message that has no board card yet
 *   approval    - a card waiting approval got an "approve" reply or a
 *                 :white_check_mark: reaction from a signed-in person
 *   reply       - a card that needs info got a reply from a person
 *   retry       - a failed card got a "retry" reply, and has attempts left
 */
class AgentQueue
{
    public const MAX_ATTEMPTS = 2;

    public function pending(): array
    {
        $items = [];

        $carded = BoardCard::pluck('request_ts')->all();
        ChannelMessage::whereNull('thread_ts')->where('is_agent', false)
            ->whereNotIn('ts', $carded)->orderBy('ts')->limit(20)->get()
            ->each(function (ChannelMessage $m) use (&$items) {
                $items[] = ['kind' => 'new_request', 'request_ts' => $m->ts, 'card_id' => null, 'user_name' => $m->author, 'text' => $m->text];
            });

        foreach (BoardCard::whereIn('status', ['Waiting approval', 'Needs info', 'Failed'])->get() as $card) {
            if ($card->status === 'Waiting approval') {
                $approval = $this->approval($card);
                if ($approval['approved']) {
                    $items[] = ['kind' => 'approval', 'request_ts' => $card->request_ts, 'card_id' => $card->id, 'user_name' => $approval['approved_by'], 'text' => $approval['evidence']];
                }

                continue;
            }
            $reply = $this->humanRepliesSince($card)->first(
                fn ($r) => $card->status === 'Needs info' || preg_match('/^\s*retry\b/i', $r->text)
            );
            if ($reply && ($card->status === 'Needs info' || $card->attempts < self::MAX_ATTEMPTS)) {
                $items[] = ['kind' => $card->status === 'Needs info' ? 'reply' : 'retry', 'request_ts' => $card->request_ts, 'card_id' => $card->id, 'user_name' => $reply->author, 'text' => $reply->text];
            }
        }

        return $items;
    }

    /**
     * Has a signed-in person approved this card since it started waiting?
     * This is the hard gate: the deploy script refuses to run unless it says yes.
     *
     * @return array{approved: bool, approved_by: ?string, evidence: ?string, at: ?string}
     */
    public function approval(BoardCard $card): array
    {
        $none = ['approved' => false, 'approved_by' => null, 'evidence' => null, 'at' => null];
        if (! in_array($card->status, ['Waiting approval', 'Deploying'], true) || ! $card->status_changed_at) {
            return $none;
        }
        // Approvals must come after the agent asked, i.e. after "Waiting approval" began.
        $since = $card->waiting_since;
        if (! $since) {
            return $none;
        }

        $reply = $this->humanRepliesSince($card, $since)->first(fn ($r) => preg_match('/^\s*approved?\b/i', $r->text));
        if ($reply) {
            return ['approved' => true, 'approved_by' => $reply->author, 'evidence' => 'thread reply: '.$reply->text, 'at' => $reply->created_at->toIso8601String()];
        }

        $request = $card->requestMessage();
        $reaction = $request?->reactions()->where('name', 'white_check_mark')->where('is_agent', false)
            ->whereNotNull('user_id')->where('created_at', '>=', $since)->orderBy('created_at')->first();
        if ($reaction) {
            return ['approved' => true, 'approved_by' => $reaction->author, 'evidence' => 'reaction :white_check_mark:', 'at' => $reaction->created_at->toIso8601String()];
        }

        return $none;
    }

    private function humanRepliesSince(BoardCard $card, ?Carbon $since = null)
    {
        return ChannelMessage::where('thread_ts', $card->request_ts)->where('is_agent', false)
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $since ?? $card->status_changed_at)
            ->orderBy('ts')->get();
    }
}
