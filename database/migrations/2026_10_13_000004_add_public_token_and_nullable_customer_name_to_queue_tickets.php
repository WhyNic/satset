<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('queue_tickets', 'public_token')) {
            Schema::table('queue_tickets', function (Blueprint $table): void {
                $table->uuid('public_token')->nullable()->after('id');
            });

            DB::table('queue_tickets')
                ->whereNull('public_token')
                ->orderBy('id')
                ->each(function (object $ticket): void {
                    DB::table('queue_tickets')
                        ->where('id', $ticket->id)
                        ->update(['public_token' => (string) Str::uuid()]);
                });

            Schema::table('queue_tickets', function (Blueprint $table): void {
                $table->unique('public_token');
            });
        }

        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table): void {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
            $table->string('customer_name')->nullable(false)->change();
        });
    }
};
