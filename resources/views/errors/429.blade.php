@include('errors._layout', [
    'title' => __('core::front.error_429_title'),
    'body' => __('core::front.error_429_body'),
    'actionUrl' => '/',
    'actionLabel' => __('core::front.error_back_home'),
])
