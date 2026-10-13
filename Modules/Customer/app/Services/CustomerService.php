<?php

declare(strict_types=1);

namespace Modules\Customer\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Models\Customer;

/**
 * Guest checkout (brief §4): a booking never requires an account up front.
 * This is the one place a Customer row gets created or matched by email,
 * so BookingService doesn't duplicate that matching logic.
 */
final class CustomerService
{
    /**
     * @param array{email: string, full_name: string, phone?: string|null, nationality?: string|null, passport_number?: string|null, locale_preference?: string|null} $data
     */
    public function findOrCreateGuest(array $data): Customer
    {
        $customer = Customer::query()->where('email', $data['email'])->first();

        if ($customer === null) {
            try {
                // Nested transaction = savepoint: losing the race below
                // undoes only this insert, not the caller's booking.
                return DB::transaction(fn () => Customer::query()->create($data));
            } catch (UniqueConstraintViolationException) {
                // The same new customer checked out twice at once (two
                // tabs, two tuk tuks) and the other checkout created them
                // first. Read it with a lock: a plain read inside the
                // caller's transaction keeps its earlier snapshot (MySQL
                // REPEATABLE READ) and would not see that newer row.
                $customer = Customer::query()->where('email', $data['email'])->lockForUpdate()->firstOrFail();
            }
        }

        // Guest checkout proves nothing about who owns an email address, so
        // a checkout may only fill in what an existing customer is missing —
        // never replace it. Otherwise anyone who knows a customer's email
        // could rewrite their name, phone and passport number (and with
        // them every earlier booking, receipt and admin view).
        foreach (['full_name', 'phone', 'nationality', 'passport_number', 'locale_preference'] as $field) {
            if (blank($customer->{$field}) && filled($data[$field] ?? null)) {
                $customer->{$field} = $data[$field];
            }
        }

        $customer->save();

        return $customer;
    }
}
