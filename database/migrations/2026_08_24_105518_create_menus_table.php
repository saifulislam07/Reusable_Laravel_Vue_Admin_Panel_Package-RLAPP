<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the 'database' driver of App\Support\Admin\MenuManager (see
     * Phase 1). Deliberately generic -- "menus", not "admin_kit_menus" --
     * per the Package-Ready Conventions.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('icon')->nullable();

            // Exactly one of these two is set: a named route resolved at
            // render time (survives URL changes), or a raw external/static
            // URL when there's no route to point at.
            $table->string('route')->nullable();
            $table->string('url')->nullable();

            $table->foreignId('parent_id')->nullable()->constrained('menus')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);

            // Null means visible to anyone who can reach the admin panel.
            $table->string('permission_name')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
