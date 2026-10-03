<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->string('ip', 45);
            $table->boolean('successful')->default(false);
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            // Both queried independently by the lockout middleware on every
            // login POST ("N failed attempts for this email/IP in the last
            // M minutes") — see docs/03-database-schema.md.
            $table->index(['email', 'created_at']);
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
