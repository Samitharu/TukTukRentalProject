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

        // An existing customer's core identity fields are refreshed from
        // the latest booking's driver details (nationality/passport can
        // change trip to trip; the account itself does not change).
        $customer->fill([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? $customer->phone,
            'nationality' => $data['nationality'] ?? $customer->nationality,
            'passport_number' => $data['passport_number'] ?? $customer->passport_number,
            'locale_preference' => $data['locale_preference'] ?? $customer->locale_preference,
        ])->save();

        return $customer;
    }
}
