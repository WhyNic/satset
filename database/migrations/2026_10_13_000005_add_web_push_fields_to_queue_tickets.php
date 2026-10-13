<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->string('phone_number')->nullable()->change();

            if (! Schema::hasColumn('queue_tickets', 'three_ahead_notified_at')) {
                $table->timestamp('three_ahead_notified_at')->nullable()->after('almost_turn_notified_at');
            }

            if (! Schema::hasColumn('queue_tickets', 'one_ahead_notified_at')) {
                $table->timestamp('one_ahead_notified_at')->nullable()->after('three_ahead_notified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->string('phone_number')->nullable(false)->change();
            $table->dropColumn(['three_ahead_notified_at', 'one_ahead_notified_at']);
        });
    }
};
