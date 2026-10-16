<x-app-layout :title="__('Profile')" width="form">
    <x-ui.card>
        @include('profile.partials.update-profile-information-form')
    </x-ui.card>

    <x-ui.card>
        @include('profile.partials.update-password-form')
    </x-ui.card>

    <x-ui.card>
        @include('profile.partials.delete-user-form')
    </x-ui.card>
</x-app-layout>
