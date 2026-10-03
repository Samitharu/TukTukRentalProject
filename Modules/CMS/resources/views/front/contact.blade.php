<x-core::layouts.public :title="__('core::front.contact_title').' · '.config('app.name')" :description="__('core::front.contact_intro')">
    <section class="section">
        <div class="container" style="max-width:34rem;">
            <h1>{{ __('core::front.contact_title') }}</h1>
            <p class="text-muted">{{ __('core::front.contact_intro') }}</p>

            <p>
                <a href="https://wa.me/{{ config('core.business.whatsapp') }}" class="btn btn--primary">{{ __('core::front.whatsapp') }}</a>
            </p>

            @if (session('status'))
                <div class="alert alert--success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('contact.store') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="name">{{ __('core::front.contact_name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="email">{{ __('core::front.contact_email') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="subject">{{ __('core::front.contact_subject') }}</label>
                    <input type="text" id="subject" name="subject" value="{{ old('subject') }}">
                </div>

                <div class="field">
                    <label for="message">{{ __('core::front.contact_message') }}</label>
                    <textarea id="message" name="message" required>{{ old('message') }}</textarea>
                    @error('message')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                {{-- Honeypot — real visitors never see this field. --}}
                <div class="field field--honeypot" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <button type="submit" class="btn btn--primary btn--block">{{ __('core::front.contact_send') }}</button>
            </form>
        </div>
    </section>
</x-core::layouts.public>
