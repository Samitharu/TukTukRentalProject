<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Deliberately a single always-one-row table, not a generic
        // key/value settings store — this holds exactly two admin-uploaded
        // images (logo, homepage hero) ahead of the full Settings screen
        // (Phase 7); a generic store would be premature for two columns.
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
