@include('errors._layout', [
    'title' => __('core::front.error_500_title'),
    'body' => __('core::front.error_500_body'),
    'actionUrl' => '/',
    'actionLabel' => __('core::front.error_back_home'),
])
