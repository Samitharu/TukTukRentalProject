<div class="admin-form-field">
    <label for="kind">{{ __('Type') }}</label>
    <select id="kind" name="kind" required>
        <option value="vehicle" @selected($current === 'vehicle')>{{ __('Tuk tuks — rented by the day') }}</option>
        <option value="stay" @selected($current === 'stay')>{{ __('Stays (cabanas, rooms) — booked by the night') }}</option>
    </select>
    <p style="color:#5b6b64;font-size:0.85em;margin-top:0.25rem;">{{ __('Stays have their own Google Maps location and are booked with check-in / check-out dates. The type can\'t be changed once the category has units.') }}</p>
</div>
