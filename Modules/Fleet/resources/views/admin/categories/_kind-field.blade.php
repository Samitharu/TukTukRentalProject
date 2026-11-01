@php
    $lines = \Modules\Package\Models\ProductCategory::query()
        ->whereIn('kind', \Modules\Fleet\Models\VehicleCategory::KINDS)
        ->orderBy('sort_order')
        ->get();
    $category = $category ?? null;
    $currentLine = (string) old('product_category_id', $category?->product_category_id ?? $lines->firstWhere('kind', $current)?->id);
@endphp
<div class="admin-form-field">
    <label for="kind">{{ __('Type') }}</label>
    <select id="kind" name="kind" required>
        <option value="vehicle" @selected($current === 'vehicle')>{{ __('Rental vehicles (tuk tuks, scooters, cars…) — rented by the day') }}</option>
        <option value="stay" @selected($current === 'stay')>{{ __('Stays (cabanas, rooms) — booked by the night') }}</option>
        <option value="transfer" @selected($current === 'transfer')>{{ __('Airport vehicles (with driver) — booked by time slot') }}</option>
    </select>
    <p style="color:#5b6b64;font-size:0.85em;margin-top:0.25rem;">{{ __('Stays have their own Google Maps location and are booked with check-in / check-out dates. The type can\'t be changed once the category has units.') }}</p>
</div>

<div class="admin-form-field">
    <label for="product_category_id">{{ __('Product line') }}</label>
    <select id="product_category_id" name="product_category_id" required>
        @foreach ($lines as $line)
            <option value="{{ $line->id }}" data-kind="{{ $line->kind }}" @selected($currentLine === (string) $line->id)>{{ $line->name }}</option>
        @endforeach
    </select>
    <p style="color:#5b6b64;font-size:0.85em;margin-top:0.25rem;">{{ __('Which line on the website these units belong to — e.g. scooters under "Vehicle Rent", tuk tuks under "Tuk Tuk Rental". A package that is not restricted to specific units only hands out units of its own line. The line must match the type above.') }}</p>
</div>
