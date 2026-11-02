<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('category');
            $table->decimal('price', 10, 2);

            $table->unsignedInteger('total_quantity');
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->unsignedInteger('max_per_booking')->default(5);

            $table->timestamps();

            $table->index(['event_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
