<x-core::layouts.public :title="__('core::front.booking_confirmation_title').' · '.config('app.name')">
    <section class="section booking-flow">
        <div class="container" style="max-width:36rem;">
            <div class="alert alert--success">
                <h1 style="margin-bottom:var(--space-2);">{{ __('core::front.booking_confirmation_title') }}</h1>
                <p>{{ __('core::front.booking_confirmation_intro') }}</p>
            </div>

            <div class="card" style="margin-bottom:var(--space-4);">
                <div class="card__body">
                    <p class="text-muted" style="margin-bottom:0;">{{ __('core::front.booking_confirmation_reference') }}</p>
                    <p style="font-size:var(--font-size-2xl);font-weight:800;letter-spacing:0.05em;">{{ $booking->reference }}</p>

                    <dl style="display:grid;grid-template-columns:auto 1fr;gap:0.5rem 1rem;margin:0;">
                        <dt class="text-muted">{{ __('core::front.booking_review_dates') }}</dt>
                        <dd>{{ $booking->start_at->format('d M Y') }} &rarr; {{ $booking->end_at->format('d M Y') }}</dd>

                        <dt class="text-muted">{{ __('core::front.booking_review_package') }}</dt>
                        <dd>{{ $booking->package?->name }}</dd>

                        <dt class="text-muted">{{ __('core::front.fleet_title') }}</dt>
                        <dd>{{ $booking->vehicle->name }} ({{ $booking->vehicle->plate_no }})</dd>

                        <dt class="text-muted">{{ __('core::front.price_total') }}</dt>
                        <dd>{{ $booking->total_amount }} {{ $booking->currency_code }}</dd>
                    </dl>

                    <a href="{{ route('booking.receipt', ['reference' => $booking->reference]) }}" class="btn btn--secondary btn--block" style="margin-top:var(--space-4);" download>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 19.5h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        {{ __('core::front.booking_download_receipt') }}
                    </a>
                </div>
            </div>

            <p>{{ __('core::front.booking_confirmation_next_steps') }}</p>

            @if (session('review_submitted') || $review || $canReview)
            <div id="review" class="card review-card">
                <div class="card__body">
                    @if (session('review_submitted'))
                        <div class="review-card__success" role="status">
                            <span class="review-card__success-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <p>{{ __('core::front.review_thanks') }}</p>
                        </div>
                    @elseif ($review)
                        <div class="review-card__existing">
                            <span class="stars-static" aria-hidden="true">{{ str_repeat('★', $review->rating) }}<span>{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                            <p>{{ __('core::front.review_already', ['rating' => $review->rating]) }}</p>
                        </div>
                    @elseif ($canReview)
                        <div class="review-card__heading">
                            <span class="review-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m12 3 2.75 5.58 6.16.9-4.45 4.34 1.05 6.13L12 17.06l-5.51 2.89 1.05-6.13-4.45-4.34 6.16-.9L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></span>
                            <div>
                                <h2>{{ __('core::front.review_title') }}</h2>
                                <p>{{ __('core::front.review_intro') }}</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('booking.feedback.store', ['reference' => $booking->reference]) }}" class="review-form">
                            @csrf

                            <fieldset class="field star-rating">
                                <legend>{{ __('core::front.review_rating_label') }}</legend>
                                {{-- Reverse DOM order (5→1) + row-reverse flex lets pure CSS
                                     fill every star up to the hovered/checked one. --}}
                                <div class="star-rating__stars">
                                    @for ($i = 5; $i >= 1; $i--)
                                        <input type="radio" id="rating-{{ $i }}" name="rating" value="{{ $i }}" class="visually-hidden" @checked((int) old('rating') === $i) required>
                                        <label for="rating-{{ $i }}" title="{{ trans_choice('core::front.review_star', $i, ['count' => $i]) }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.85 5.8 6.4.93-4.63 4.5 1.1 6.37L12 17.4l-5.72 3 1.1-6.37-4.63-4.5 6.4-.93L12 2.8Z"/></svg>
                                            <span class="visually-hidden">{{ trans_choice('core::front.review_star', $i, ['count' => $i]) }}</span>
                                        </label>
                                    @endfor
                                </div>
                                @error('rating')<p class="field-error">{{ $message }}</p>@enderror
                            </fieldset>

                            <div class="field review-form__comment">
                                <label for="comment">{{ __('core::front.review_comment_label') }}</label>
                                <textarea id="comment" name="comment" maxlength="500" rows="4" placeholder="{{ __('core::front.review_comment_placeholder') }}">{{ old('comment') }}</textarea>
                                @error('comment')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <button type="submit" class="btn btn--primary btn--block">{{ __('core::front.review_submit') }}</button>
                        </form>
                    @endif
                </div>
            </div>
            @endif

            <a href="{{ route('home') }}" class="btn btn--primary btn--block">{{ __('core::front.booking_confirmation_back_home') }}</a>
        </div>
    </section>
</x-core::layouts.public>
