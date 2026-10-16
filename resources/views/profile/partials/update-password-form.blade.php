<section aria-labelledby="update-password-title">
    <header>
        <h2 id="update-password-title" class="text-base font-semibold text-ink">{{ __('Update Password') }}</h2>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('put')

        <x-ui.field :label="__('Current Password')" for="update_password_current_password" error="current_password" error-bag="updatePassword">
            <x-ui.input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" />
        </x-ui.field>

        <x-ui.field :label="__('New Password')" for="update_password_password" error="password" error-bag="updatePassword">
            <x-ui.input id="update_password_password" name="password" type="password" autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field :label="__('Confirm Password')" for="update_password_password_confirmation" error="password_confirmation" error-bag="updatePassword">
            <x-ui.input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
        </x-ui.field>

        <div class="flex items-center gap-4">
            <x-ui.button>{{ __('Save') }}</x-ui.button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm font-medium text-success" role="status">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
