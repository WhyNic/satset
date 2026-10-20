<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->string('email', 254)->nullable()->index();
        });

        Schema::create('ticket_email_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('queue_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('event', 32);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['queue_ticket_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_email_events');
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->dropIndex(['email']);
            $table->dropColumn('email');
        });
    }
};
