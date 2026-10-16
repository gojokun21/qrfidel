<x-guest-layout :title="__('Confirm Password')">
    <p class="mb-4 text-sm text-ink-secondary">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <x-ui.field :label="__('Password')" for="password">
            <x-ui.input id="password" type="password" name="password" required autocomplete="current-password" />
        </x-ui.field>

        <div class="flex justify-end">
            <x-ui.button>{{ __('Confirm') }}</x-ui.button>
        </div>
    </form>
</x-guest-layout>
