<x-guest-layout :title="__('Reset Password')">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.field :label="__('Email')" for="email">
            <x-ui.input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
        </x-ui.field>

        <x-ui.field :label="__('Password')" for="password">
            <x-ui.input id="password" type="password" name="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field :label="__('Confirm Password')" for="password_confirmation">
            <x-ui.input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
        </x-ui.field>

        <div class="flex justify-end">
            <x-ui.button>{{ __('Reset Password') }}</x-ui.button>
        </div>
    </form>
</x-guest-layout>
