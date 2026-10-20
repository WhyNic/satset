<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_party_size')->default(6);
            $table->boolean('queue_open')->default(true);
        });
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->unsignedSmallInteger('party_size')->default(1);
        });
        Schema::table('whatsapp_logs', function (Blueprint $table): void {
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key')->nullable()->unique();
            $table->string('notification_field')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->index('provider_message_id');
            $table->index(['direction', 'status']);
        });
        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('phone_number', 20)->unique();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state')->default('restaurant_selected');
            $table->unsignedSmallInteger('party_size')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_incoming_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_message_id')->unique();
            $table->string('phone_number', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_incoming_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::table('whatsapp_logs', function (Blueprint $table): void {
            $table->dropForeign(['restaurant_id']);
            $table->dropIndex(['provider_message_id']);
            $table->dropIndex(['direction', 'status']);
            $table->dropUnique(['event_key']);
            $table->dropColumn(['restaurant_id', 'event_key', 'notification_field', 'attempts', 'error_code', 'sent_at']);
        });
        Schema::table('queue_tickets', fn (Blueprint $table) => $table->dropColumn('party_size'));
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn(['max_party_size', 'queue_open']));
    }
};
