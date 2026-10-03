<x-admin::layouts.app :title="__('Branding')">
    <div class="admin-card">
        <h2>{{ __('Logo') }}</h2>
        <p style="color:#5b6b64;">{{ __('Shown in the site header, in place of the text brand name. Recommended: a transparent PNG or WebP, roughly 3:1 width-to-height.') }}</p>

        @if ($settings->logoUrl())
            <div style="margin-bottom:1rem;">
                <img src="{{ $settings->logoUrl() }}" alt="" style="max-height:60px;background:#16231d;padding:0.5rem;border-radius:0.5rem;">
            </div>
            <form method="POST" action="{{ route('admin.branding.logo.destroy') }}" onsubmit="return confirm('{{ __('Remove the logo and go back to the text brand name?') }}');" style="margin-bottom:1rem;">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Remove logo') }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="logo">{{ __('Upload new logo') }}</label>
                <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
                @error('logo')<p style="color:#b3261e;">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="admin-btn">{{ __('Save') }}</button>
        </form>
    </div>

    <div class="admin-card">
        <h2>{{ __('Homepage hero image') }}</h2>
        <p style="color:#5b6b64;">{{ __('Shown beside the homepage headline. Recommended: a landscape photo at least 1600px wide. Leave unset to use the plain colour background instead.') }}</p>

        @if ($settings->heroImageUrl())
            <div style="margin-bottom:1rem;">
                <img src="{{ $settings->heroImageUrl() }}" alt="" style="max-width:320px;border-radius:0.5rem;">
            </div>
            <form method="POST" action="{{ route('admin.branding.hero-image.destroy') }}" onsubmit="return confirm('{{ __('Remove the hero image?') }}');" style="margin-bottom:1rem;">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--secondary">{{ __('Remove hero image') }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="admin-form-field">
                <label for="hero_image">{{ __('Upload new hero image') }}</label>
                <input type="file" id="hero_image" name="hero_image" accept="image/jpeg,image/png,image/webp">
                @error('hero_image')<p style="color:#b3261e;">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="admin-btn">{{ __('Save') }}</button>
        </form>
    </div>
</x-admin::layouts.app>
