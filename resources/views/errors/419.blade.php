{{-- Sesiune expirată sau token CSRF vechi (de ex. după o deconectare în alt tab). Formularul nu s-a trimis. --}}
<x-error-page title="Sesiunea a expirat" code="419"
              message="Pagina a stat prea mult deschisă sau te-ai deconectat între timp, așa că acțiunea nu s-a salvat. Reîncarcă pagina și încearcă din nou.">
    <x-ui.button :href="url()->previous()">Înapoi</x-ui.button>
</x-error-page>
