<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_reviews', function (Blueprint $table) {
            // Drop the existing single-column index
            $table->dropIndex(['provider_profile_id']);

            // Add composite index for filtering visible reviews by provider
            $table->index(['provider_profile_id', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::table('service_reviews', function (Blueprint $table) {
            // Drop the composite index
            $table->dropIndex(['provider_profile_id', 'is_visible']);

            // Restore the original single-column index
            $table->index('provider_profile_id');
        });
    }
};
