{{-- Bannerul unei cereri de ștampilă care așteaptă confirmarea angajatului (pagina cardului și tejgheaua). --}}
<div role="status" {{ $attributes->class('flex items-center gap-3 rounded-card border border-primary-line bg-primary-soft px-4 py-3 text-sm text-ink') }}>
    <span class="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-primary" aria-hidden="true"></span>
    <span>{{ $slot }}</span>
</div>
