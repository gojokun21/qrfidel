{{-- Mesajele din abort(403, '…') sunt deja în română; cele implicite (Policies, rutele `signed`) sunt în engleză și se înlocuiesc. --}}
@php
    $reason = $exception->getMessage();
    $message = match ($reason) {
        '', 'Forbidden', 'This action is unauthorized.' => 'Contul tău nu are drepturi pentru această pagină. Dacă crezi că e o greșeală, vorbește cu ownerul afacerii.',
        'Invalid signature.' => 'Linkul nu mai e valabil: a expirat sau a fost modificat. Cere unul nou.',
        default => $reason,
    };
@endphp
<x-error-page title="Nu ai acces la această pagină" :message="$message" code="403" />
