{{-- Mesajele din abort(403, '…') sunt deja în română; cel implicit al Policies e în engleză și se înlocuiește. --}}
@php
    $reason = $exception->getMessage();
    $message = in_array($reason, ['', 'Forbidden', 'This action is unauthorized.'], true)
        ? 'Contul tău nu are drepturi pentru această pagină. Dacă crezi că e o greșeală, vorbește cu ownerul afacerii.'
        : $reason;
@endphp
<x-error-page title="Nu ai acces la această pagină" :message="$message" code="403" />
