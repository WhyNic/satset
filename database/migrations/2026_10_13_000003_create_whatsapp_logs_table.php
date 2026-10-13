<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('queue_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number')->nullable();
            $table->string('direction');
            $table->text('message');
            $table->string('status');
            $table->string('provider_message_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
