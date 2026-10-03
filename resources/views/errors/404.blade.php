@include('errors._layout', [
    'title' => __('core::front.error_404_title'),
    'body' => __('core::front.error_404_body'),
    'actionUrl' => '/',
    'actionLabel' => __('core::front.error_back_home'),
])
