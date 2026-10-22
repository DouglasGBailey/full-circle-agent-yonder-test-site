<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One test failure turned into a ticket. Proposed by the agent, Open once a
 * person approves it, then Fixed or Still failing after the next test run
 * (Dropped if the card gives up instead).
 */
class BoardTicket extends Model
{
    public const STATUSES = ['Proposed', 'Open', 'Fixed', 'Still failing', 'Dropped'];

    protected $fillable = ['board_card_id', 'round', 'title', 'detail', 'status', 'approved_by'];

    protected function casts(): array
    {
        return ['round' => 'integer'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(BoardCard::class, 'board_card_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'round' => $this->round,
            'title' => $this->title,
            'detail' => $this->detail,
            'status' => $this->status,
            'approved_by' => $this->approved_by,
        ];
    }
}
