<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable();
            $table->char('email_verification_hash', 64)->nullable();
        });

        // Tickets that already have an email before verification existed were given by their customers.
        DB::table('queue_tickets')->whereNotNull('email')->update(['email_verified_at' => DB::raw('created_at')]);

        Schema::create('staff_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['restaurant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_actions');
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->dropColumn(['email_verified_at', 'email_verification_hash']);
        });
    }
};
