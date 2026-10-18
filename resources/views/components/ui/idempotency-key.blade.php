{{--
    Cheia de idempotency a unui formular de creare (App\Services\Loyalty\IdempotentCreation):
    un dublu-click sau o retrimitere cu aceeași cheie nu creează a doua oară.
    După o eroare de validare păstrează cheia trimisă; nimic nu fusese creat cu ea.
--}}
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
