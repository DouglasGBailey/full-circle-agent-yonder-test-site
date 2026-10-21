<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * Where the chat widget is in its flow for one browser session. See the
 * create_chat_states migration for why this is not kept in the session.
 */
class ChatState
{
    private const TABLE = 'chat_states';

    public static function load(string $sessionId): array
    {
        $row = DB::table(self::TABLE)
            ->where('session_hash', self::hash($sessionId))
            ->where('updated_at', '>=', now()->subMinutes(self::lifetimeMinutes()))
            ->value('state');
        $state = $row ? json_decode($row, true) : null;

        return is_array($state) ? $state : [];
    }

    public static function save(string $sessionId, array $state): void
    {
        DB::table(self::TABLE)->upsert(
            [['session_hash' => self::hash($sessionId), 'state' => json_encode($state, JSON_THROW_ON_ERROR), 'updated_at' => now()]],
            ['session_hash'],
            ['state', 'updated_at'],
        );

        // Now and then, drop conversations nobody has touched for a session lifetime.
        if (random_int(1, 50) === 1) {
            DB::table(self::TABLE)->where('updated_at', '<', now()->subMinutes(self::lifetimeMinutes()))->delete();
        }
    }

    public static function forget(string $sessionId): void
    {
        DB::table(self::TABLE)->where('session_hash', self::hash($sessionId))->delete();
    }

    private static function hash(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    private static function lifetimeMinutes(): int
    {
        return max(1, (int) config('session.lifetime', 120));
    }
}
