<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // New accounts get the least privilege; existing accounts were full admins and become owners.
            $table->string('role', 20)->default('outlet_admin')->after('email');
            $table->boolean('active')->default(true)->after('role');
        });
        DB::table('users')->update(['role' => 'owner']);

        Schema::create('restaurant_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'restaurant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_user');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'active']);
        });
    }
};
