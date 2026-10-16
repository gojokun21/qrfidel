<section aria-labelledby="delete-account-title" class="space-y-5">
    <header>
        <h2 id="delete-account-title" class="text-base font-semibold text-ink">{{ __('Delete Account') }}</h2>
        <p class="mt-1 text-sm text-ink-muted">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-ui.button type="button" variant="danger" aria-controls="modal-confirm-user-deletion" aria-haspopup="dialog" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        {{ __('Delete Account') }}
    </x-ui.button>

    <x-ui.modal name="confirm-user-deletion" :title="__('Are you sure you want to delete your account?')" :show="$errors->userDeletion->isNotEmpty()">
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <p class="text-sm text-ink-muted">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <x-ui.field :label="__('Password')" for="password" error-bag="userDeletion" class="mt-5">
                <x-ui.input id="password" name="password" type="password" autocomplete="current-password" />
            </x-ui.field>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger">{{ __('Delete Account') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</section>
