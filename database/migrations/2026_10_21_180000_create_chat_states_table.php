<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The chat widget's conversation state, one row per browser session.
 *
 * It lives outside the session payload on purpose: Laravel writes the whole
 * session back when each request ends, so any request that overlaps a chat
 * turn (a page load, a poll, a prefetch) could otherwise put back an older
 * chat state and lose the visitor's place in the flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_states', function (Blueprint $table) {
            $table->string('session_hash', 64)->primary();   // sha256 of the session id, never the id itself
            $table->text('state');
            $table->timestamp('updated_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_states');
    }
};
