<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_token')->unique();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('queue_number')->unique();
            $table->unsignedInteger('sequence_number');
            $table->string('status')->default('waiting')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('almost_turn_notified_at')->nullable();
            $table->timestamp('three_ahead_notified_at')->nullable();
            $table->timestamp('one_ahead_notified_at')->nullable();
            $table->timestamp('called_notified_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status', 'sequence_number']);
            $table->unique(['restaurant_id', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};
