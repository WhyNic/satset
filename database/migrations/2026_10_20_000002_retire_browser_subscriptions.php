<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }

    public function down(): void
    {
        // Obsolete browser subscriptions cannot be restored by rolling back.
    }
};
