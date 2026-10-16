<x-guest-layout :title="__('Register')">
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <x-ui.field :label="__('Name')" for="name">
            <x-ui.input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
        </x-ui.field>

        <x-ui.field :label="__('Email')" for="email">
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
        </x-ui.field>

        <x-ui.field :label="__('Password')" for="password">
            <x-ui.input id="password" type="password" name="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field :label="__('Confirm Password')" for="password_confirmation">
            <x-ui.input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
        </x-ui.field>

        <div class="flex flex-wrap items-center justify-end gap-4">
            <x-ui.link :href="route('login')" class="text-sm">{{ __('Already registered?') }}</x-ui.link>
            <x-ui.button>{{ __('Register') }}</x-ui.button>
        </div>
    </form>
</x-guest-layout>
