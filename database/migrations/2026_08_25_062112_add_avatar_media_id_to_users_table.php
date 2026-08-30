<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Closes the loop Phase 8 left open ("avatar upload deliberately
     * skipped, waiting on Phase 12"): an avatar is a Media row picked from
     * the library via <x-admin::media-picker>, not a separate per-user
     * upload flow. nullOnDelete() so removing the underlying Media item
     * just clears the avatar rather than leaving a dangling reference.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('avatar_media_id')
                ->nullable()
                ->after('password')
                ->constrained('media')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avatar_media_id');
        });
    }
};
