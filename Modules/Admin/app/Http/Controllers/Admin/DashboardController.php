<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Placeholder for Phase 7's real dashboard (today's pickups/returns, revenue,
 * occupancy, alerts — none of which exist as data sources yet in Phase 2).
 */
final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin::admin.dashboard.index');
    }
}
