<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body');
            $table->string('cover_image')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            // The public blog list's exact filter+sort.
            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
