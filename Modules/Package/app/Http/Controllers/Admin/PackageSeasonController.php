<?php

declare(strict_types=1);

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Package\Http\Requests\Admin\StoreSeasonRequest;
use Modules\Package\Models\Package;
use Modules\Package\Models\PackageSeason;
use Modules\Package\Services\PackageSeasonValidator;

final class PackageSeasonController extends Controller
{
    public function __construct(private readonly PackageSeasonValidator $validator)
    {
    }

    public function store(StoreSeasonRequest $request, Package $package): RedirectResponse
    {
        $weekdayMask = array_reduce(
            $request->input('weekdays', []),
            fn (int $mask, int $weekday) => $mask | (1 << $weekday),
            0,
        );

        $newSeason = [
            'id' => null,
            'name' => $request->string('name')->toString(),
            'starts_on' => $request->string('starts_on')->toString(),
            'ends_on' => $request->string('ends_on')->toString(),
            'weekday_mask' => $weekdayMask,
        ];

        $existing = $package->seasons()->get(['id', 'name', 'starts_on', 'ends_on', 'weekday_mask'])
            ->map(fn (PackageSeason $season) => [
                'id' => $season->id,
                'name' => $season->name,
                'starts_on' => $season->starts_on->toDateString(),
                'ends_on' => $season->ends_on->toDateString(),
                'weekday_mask' => $season->weekday_mask,
            ])
            ->all();

        $errors = $this->validator->validate([...$existing, $newSeason]);

        if ($errors !== []) {
            return back()->withErrors(['name' => implode(' ', $errors)])->withInput();
        }

        $package->seasons()->create([
            'name' => $newSeason['name'],
            'starts_on' => $newSeason['starts_on'],
            'ends_on' => $newSeason['ends_on'],
            'price_modifier_type' => $request->string('price_modifier_type')->toString(),
            'price_modifier_value' => $request->input('price_modifier_value'),
            'weekday_mask' => $weekdayMask,
            'priority' => $request->integer('priority', 0),
        ]);

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Season added.'));
    }

    public function destroy(Package $package, PackageSeason $season): RedirectResponse
    {
        $this->authorize('update', $package);

        abort_unless($season->package_id === $package->id, 404);

        $season->delete();

        return redirect()->route('admin.packages.edit', $package)->with('status', __('Season removed.'));
    }
}
