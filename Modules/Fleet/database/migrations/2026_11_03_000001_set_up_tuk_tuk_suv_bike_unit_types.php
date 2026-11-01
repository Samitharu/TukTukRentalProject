<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Fleet\Support\DefaultUnitTypes;

return new class () extends Migration {
    /**
     * Tuk Tuk / SUV / Bike instead of "Standard" / "Premium" (both were
     * tuk tuks): merged into Tuk Tuk, vehicles and packages kept.
     */
    public function up(): void
    {
        DefaultUnitTypes::install();
    }

    public function down(): void
    {
        // The merge is not undone: the old split carried no meaning.
    }
};
