@include('errors._layout', [
    'title' => __('core::front.error_419_title'),
    'body' => __('core::front.error_419_body'),
    'actionUrl' => url()->previous('/'),
    'actionLabel' => __('core::front.error_try_again'),
])
