<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCategory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FleetController extends Controller
{
    public function index(Request $request): View
    {
        $vehicles = Vehicle::query()
            ->active()
            ->with(['images', 'category'])
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category',
                fn ($q2) => $q2->where('id', $request->integer('category')),
            ))
            ->orderBy('plate_no')
            ->get();

        $categories = VehicleCategory::query()->active()->orderBy('sort_order')->get();

        return view('fleet::front.index', compact('vehicles', 'categories'));
    }

    public function show(string $locale, string $slug): View
    {
        $vehicle = Vehicle::findBySlug($locale, $slug);

        if ($vehicle === null || $vehicle->status !== Vehicle::STATUS_ACTIVE) {
            throw new NotFoundHttpException;
        }

        $vehicle->load('images', 'category');

        return view('fleet::front.show', compact('vehicle'));
    }
}
