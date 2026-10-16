<section aria-labelledby="profile-information-title">
    <header>
        <h2 id="profile-information-title" class="text-base font-semibold text-ink">{{ __('Profile Information') }}</h2>
        <p class="mt-1 text-sm text-ink-muted">{{ __("Update your account's profile information and email address.") }}</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <x-ui.field :label="__('Name')" for="name">
            <x-ui.input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
        </x-ui.field>

        <x-ui.field :label="__('Email')" for="email">
            <x-ui.input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
        </x-ui.field>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <x-ui.alert tone="warning">
                {{ __('Your email address is unverified.') }}
                <button form="send-verification" class="font-semibold underline underline-offset-4 hover:no-underline">
                    {{ __('Click here to re-send the verification email.') }}
                </button>
            </x-ui.alert>

            @if (session('status') === 'verification-link-sent')
                <x-ui.alert tone="success">{{ __('A new verification link has been sent to your email address.') }}</x-ui.alert>
            @endif
        @endif

        <div class="flex items-center gap-4">
            <x-ui.button>{{ __('Save') }}</x-ui.button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm font-medium text-success" role="status">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
