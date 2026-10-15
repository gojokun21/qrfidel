{{-- Mesajul de succes (`status`) și refuzurile regulilor de business (eroarea `loyalty`). --}}
@if (session('status'))
    <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
@endif
@error('loyalty')
    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
@enderror
