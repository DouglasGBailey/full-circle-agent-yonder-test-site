<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tickets the agent proposes when a card's tests fail. A person approves them
     * in the thread, Claude Code fixes them, and the tests run again.
     */
    public function up(): void
    {
        Schema::create('board_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_card_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round');
            $table->string('title', 200);
            $table->text('detail')->nullable();
            $table->string('status', 20)->index();
            $table->string('approved_by', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_tickets');
    }
};
