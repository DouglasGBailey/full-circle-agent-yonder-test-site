<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message in the simulated Slack channel. A message with a thread_ts is a
 * reply in that thread; a top-level message has thread_ts = null.
 */
class ChannelMessage extends Model
{
    public const AGENT_NAME = 'Full Circle Agent';

    protected $fillable = ['ts', 'thread_ts', 'user_id', 'author', 'is_agent', 'text'];

    protected function casts(): array
    {
        return ['is_agent' => 'boolean'];
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChannelReaction::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ChannelMessage::class, 'thread_ts', 'ts')->orderBy('ts');
    }

    /** A new, unique Slack-style timestamp such as "1792500000.123456". */
    public static function newTs(): string
    {
        do {
            $ts = sprintf('%.6f', microtime(true));
        } while (static::where('ts', $ts)->exists());

        return $ts;
    }

    /** The message in the shape Slack's conversations.history returns. */
    public function toSlack(): array
    {
        $out = [
            'type' => 'message',
            'ts' => $this->ts,
            'user' => $this->is_agent ? 'U-FULL-CIRCLE-AGENT' : 'U'.str_pad((string) $this->user_id, 6, '0', STR_PAD_LEFT),
            'user_name' => $this->author,
            'is_agent' => $this->is_agent,
            'text' => $this->text,
        ];
        if ($this->thread_ts) {
            $out['thread_ts'] = $this->thread_ts;
        } else {
            $out['reply_count'] = $this->replies()->count();
        }
        $reactions = $this->reactions->groupBy('name')->map(fn ($g, $name) => [
            'name' => $name,
            'count' => $g->count(),
            'users' => $g->pluck('author')->values()->all(),
        ])->values()->all();
        if ($reactions) {
            $out['reactions'] = $reactions;
        }

        return $out;
    }
}
