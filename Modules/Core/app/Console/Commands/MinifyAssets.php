<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use MatthiasMullie\Minify\CSS as CssMinifier;
use MatthiasMullie\Minify\JS as JsMinifier;

/**
 * Pure-PHP CSS/JS minification for production, deliberately avoiding any
 * Node/Vite build step (hard constraint: small VPS / shared hosting with no
 * guaranteed Node runtime). Combines every file in public/assets/css and
 * public/assets/js (module-contributed stylesheets/scripts are published
 * there by each module) into single minified bundles under public/assets/build/,
 * which asset_v() prefers automatically when present.
 */
final class MinifyAssets extends Command
{
    protected $signature = 'assets:minify';

    protected $description = 'Combine and minify public/assets CSS and JS into public/assets/build (no Node/npm required)';

    public function handle(): int
    {
        $this->minifyGroup('css', public_path('assets/css'), public_path('assets/build/css/app.min.css'), CssMinifier::class);
        $this->minifyGroup('js', public_path('assets/js'), public_path('assets/build/js/app.min.js'), JsMinifier::class);

        $this->info('Assets minified successfully.');

        return self::SUCCESS;
    }

    /**
     * @param class-string<CssMinifier|JsMinifier> $minifierClass
     */
    private function minifyGroup(string $extension, string $sourceDir, string $destination, string $minifierClass): void
    {
        if (! File::isDirectory($sourceDir)) {
            $this->warn("Skipping {$extension}: source directory does not exist ({$sourceDir}).");

            return;
        }

        $files = collect(File::allFiles($sourceDir))
            ->filter(fn ($file) => $file->getExtension() === $extension)
            ->filter(fn ($file) => ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR))
            ->sortBy(fn ($file) => $file->getRelativePathname());

        if ($files->isEmpty()) {
            $this->warn("No .{$extension} files found under {$sourceDir}.");

            return;
        }

        /** @var CssMinifier|JsMinifier $minifier */
        $minifier = new $minifierClass();

        foreach ($files as $file) {
            $minifier->add($file->getPathname());
            $this->line("  + {$file->getRelativePathname()}");
        }

        File::ensureDirectoryExists(dirname($destination));
        $minifier->minify($destination);

        $this->info("Wrote {$destination} (".$files->count().' files combined).');
    }
}
