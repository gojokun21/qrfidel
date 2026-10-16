<x-guest-layout :title="__('Forgot your password?')">
    <p class="mb-4 text-sm text-ink-secondary">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </p>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-ui.field :label="__('Email')" for="email">
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required autofocus />
        </x-ui.field>

        <div class="flex justify-end">
            <x-ui.button>{{ __('Email Password Reset Link') }}</x-ui.button>
        </div>
    </form>
</x-guest-layout>
