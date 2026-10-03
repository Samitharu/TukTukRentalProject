<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('symbol', 5);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
