<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelReaction extends Model
{
    /** Reactions people may add from the channel page (Slack emoji names). */
    public const ALLOWED = ['white_check_mark', 'eyes', 'thumbsup', 'x', 'tada'];

    public const EMOJI = [
        'white_check_mark' => "\u{2705}",
        'eyes' => "\u{1F440}",
        'thumbsup' => "\u{1F44D}",
        'x' => "\u{274C}",
        'tada' => "\u{1F389}",
        'rocket' => "\u{1F680}",
        'hourglass' => "\u{231B}",
    ];

    protected $fillable = ['channel_message_id', 'name', 'user_id', 'author', 'is_agent'];

    protected function casts(): array
    {
        return ['is_agent' => 'boolean'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChannelMessage::class, 'channel_message_id');
    }
}
