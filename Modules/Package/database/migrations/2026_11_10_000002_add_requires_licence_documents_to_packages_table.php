<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // After booking, the customer must send their driving licence (by
        // WhatsApp or email) so the business can arrange the Sri Lankan permit.
        Schema::table('packages', function (Blueprint $table): void {
            $table->boolean('requires_licence_documents')->default(false)->after('group_pricing');
        });

        // On for the existing tuk tuk packages (staff untick any with a
        // driver, such as a guided tour). Matched by the line's English name.
        $tukTukLines = DB::table('product_categories')
            ->where('kind', 'vehicle')
            ->get(['id', 'name'])
            ->filter(fn ($line) => str_contains(mb_strtolower((string) (json_decode((string) $line->name, true)['en'] ?? '')), 'tuk tuk'))
            ->pluck('id');

        if ($tukTukLines->isNotEmpty()) {
            DB::table('packages')->whereIn('product_category_id', $tukTukLines)->update(['requires_licence_documents' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('requires_licence_documents');
        });
    }
};
