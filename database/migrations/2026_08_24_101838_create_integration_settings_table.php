<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per integration ("smtp", "google_analytics", …). `data` holds
     * that integration's whole field set as an encrypted JSON blob -- see
     * App\Models\Admin\IntegrationSetting -- so credentials (an SMTP
     * password, for instance) are never stored in the clear, without having
     * to remember to encrypt individual columns.
     */
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group')->unique();
            $table->text('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
