<x-core::layouts.master :title="($title ?? __('Sign in')).' · '.config('app.name').' Admin'">
    <x-slot:styles>
        <link rel="stylesheet" href="{{ asset_v('assets/css/admin.css') }}" nonce="{{ csp_nonce() }}">
    </x-slot:styles>

    <div class="admin-auth-page">
        <h1>{{ config('app.name') }}</h1>

        @if (session('status'))
            <div class="admin-alert admin-alert--success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="admin-alert admin-alert--error">
                <ul style="margin:0;padding-left:1.1rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </div>
</x-core::layouts.master>
