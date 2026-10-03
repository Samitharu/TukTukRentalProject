<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Customer\Models\Customer;

final class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('customer::admin.customers.index', compact('customers', 'search'));
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load('bookings');

        return view('customer::admin.customers.show', compact('customer'));
    }
}
