<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The simulated Slack channel (#yonder-requests) and the public request board.
 *
 * Messages are keyed by a Slack-style "ts" string so the mock-Slack MCP server
 * can return the same shapes as the real Slack Web API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_messages', function (Blueprint $table) {
            $table->id();
            $table->string('ts', 32)->unique();
            $table->string('thread_ts', 32)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author', 80);
            $table->boolean('is_agent')->default(false);
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('channel_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_message_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author', 80);
            $table->boolean('is_agent')->default(false);
            $table->timestamps();
            $table->unique(['channel_message_id', 'name', 'author']);
        });

        Schema::create('board_cards', function (Blueprint $table) {
            $table->id();
            $table->string('request_ts', 32)->unique();
            $table->string('title', 160);
            $table->text('request_text');
            $table->string('requested_by', 80);
            $table->string('status', 32)->default('New')->index();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('waiting_since')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('branch', 120)->nullable();
            $table->string('commit_url', 300)->nullable();
            $table->string('test_result', 300)->nullable();
            $table->string('approved_by', 80)->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_cards');
        Schema::dropIfExists('channel_reactions');
        Schema::dropIfExists('channel_messages');
    }
};
