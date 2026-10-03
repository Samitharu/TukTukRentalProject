<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_name');
            $table->string('country', 2)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->json('content');
            $table->boolean('is_approved')->default(false);
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
