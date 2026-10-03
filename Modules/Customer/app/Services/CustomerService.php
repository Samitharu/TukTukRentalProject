<?php

declare(strict_types=1);

namespace Modules\Customer\Services;

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
            return Customer::query()->create($data);
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
