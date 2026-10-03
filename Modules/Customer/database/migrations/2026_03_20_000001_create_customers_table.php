<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('full_name');
            $table->string('nationality', 2)->nullable();
            // Encrypted at rest (brief §8) via the model's 'encrypted' cast —
            // stored as a TEXT column since ciphertext is longer than the
            // plaintext passport number.
            $table->text('passport_number')->nullable();
            $table->string('password')->nullable();
            $table->string('locale_preference', 5)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
