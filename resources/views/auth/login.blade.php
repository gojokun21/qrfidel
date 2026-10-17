<x-guest-layout :title="__('Log in')" heading="Bine ai revenit" subheading="Intră în contul afacerii tale.">
    @if (session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-ui.field :label="__('Email')" for="email">
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
        </x-ui.field>

        <x-ui.field :label="__('Password')" for="password">
            <x-ui.input id="password" type="password" name="password" required autocomplete="current-password" />
        </x-ui.field>

        <x-ui.checkbox id="remember_me" name="remember" :label="__('Remember me')" />

        <div class="flex flex-wrap items-center justify-end gap-4">
            @if (Route::has('password.request'))
                <x-ui.link :href="route('password.request')" class="text-sm">{{ __('Forgot your password?') }}</x-ui.link>
            @endif

            <x-ui.button>{{ __('Log in') }}</x-ui.button>
        </div>
    </form>
</x-guest-layout>
