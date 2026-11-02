<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organizer_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('venue_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('event_type');

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('status')->default('draft');

            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['organizer_id', 'status']);
            $table->index(['venue_id', 'starts_at']);
            $table->index(['event_type', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
