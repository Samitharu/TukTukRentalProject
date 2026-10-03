<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('missing_translations_log', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('locale', 5);
            $table->string('field');
            $table->timestamp('created_at')->nullable();

            $table->index(['locale', 'created_at']);
            $table->unique(['model_type', 'model_id', 'locale', 'field'], 'missing_translations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_translations_log');
    }
};
