<x-admin::layouts.app :title="__('Add testimonial')">
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.testimonials.store') }}" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="customer_name">{{ __('Customer name') }}</label>
                <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required>
            </div>
            <div class="admin-form-field">
                <label for="country">{{ __('Country (ISO 2-letter)') }}</label>
                <input type="text" id="country" name="country" maxlength="2" value="{{ old('country') }}">
            </div>
            <div class="admin-form-field">
                <label for="rating">{{ __('Rating (1-5)') }}</label>
                <input type="number" id="rating" name="rating" min="1" max="5" value="{{ old('rating', 5) }}" required>
            </div>
            <x-admin::translatable-field name="content" :label="__('Testimonial text')" :textarea="true" :required="true" />
            <div class="admin-form-field">
                <label for="source">{{ __('Source (optional, e.g. Google, TripAdvisor)') }}</label>
                <input type="text" id="source" name="source" value="{{ old('source') }}">
            </div>
            <div class="admin-form-field">
                <label><input type="checkbox" name="is_approved" value="1" checked> {{ __('Approved (visible on site)') }}</label>
            </div>
            <button type="submit" class="admin-btn">{{ __('Add testimonial') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
