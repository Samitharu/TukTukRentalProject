<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('route_slugs', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('locale', 5);
            $table->string('slug', 191);
            $table->timestamps();

            // This unique index IS the routing mechanism: a locale-aware slug
            // lookup resolves any translatable model in one query, and it is
            // what guarantees two records can't collide on the same slug
            // within a language. See docs/03-database-schema.md.
            $table->unique(['model_type', 'locale', 'slug']);

            // Reverse lookup used to render this record's slug in every
            // locale (hreflang tags, admin live-preview, slug-change redirects).
            $table->index(['model_type', 'model_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_slugs');
    }
};
