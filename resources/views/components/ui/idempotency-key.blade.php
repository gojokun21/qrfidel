{{--
    Cheia de idempotency a unui formular de creare (App\Services\Loyalty\IdempotentCreation):
    un dublu-click sau o retrimitere cu aceeași cheie nu creează a doua oară.
    După o eroare de validare păstrează cheia trimisă; nimic nu fusese creat cu ea.
    O cheie lipsă sau stricată (formular vechi) se înlocuiește; una validă rămâne, ca o cerere încă în lucru
    să nu fie creată a doua oară cu o cheie nouă. Eroarea cheii se arată aici, câmpul fiind ascuns.
--}}
@php
    $previous = old('idempotency_key');
    $key = is_string($previous) && \Illuminate\Support\Str::isUuid($previous) ? $previous : (string) \Illuminate\Support\Str::uuid();
@endphp
@error('idempotency_key')
    <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
@enderror
<input type="hidden" name="idempotency_key" value="{{ $key }}">
