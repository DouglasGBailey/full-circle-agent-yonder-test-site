<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One change request on the public /board, created and updated only by
 * Full Circle Agent through the token API.
 */
class BoardCard extends Model
{
    public const STATUSES = ['New', 'In progress', 'Needs info', 'Waiting approval', 'Deploying', 'Deployed', 'Failed'];

    protected $fillable = [
        'request_ts', 'title', 'request_text', 'requested_by', 'status', 'status_changed_at', 'waiting_since',
        'attempts', 'branch', 'commit_url', 'test_result', 'approved_by', 'deployed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status_changed_at' => 'datetime',
            'waiting_since' => 'datetime',
            'deployed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function requestMessage(): ?ChannelMessage
    {
        return ChannelMessage::where('ts', $this->request_ts)->first();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'request_ts' => $this->request_ts,
            'title' => $this->title,
            'request_text' => $this->request_text,
            'requested_by' => $this->requested_by,
            'status' => $this->status,
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'waiting_since' => $this->waiting_since?->toIso8601String(),
            'attempts' => $this->attempts,
            'branch' => $this->branch,
            'commit_url' => $this->commit_url,
            'test_result' => $this->test_result,
            'approved_by' => $this->approved_by,
            'deployed_at' => $this->deployed_at?->toIso8601String(),
            'notes' => $this->notes,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
