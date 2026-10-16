{{--
    Paginarea aplicației (înlocuiește view-ul implicit al Laravel, folosit de `$paginator->links()`).
    Pe mobil: Înapoi / pagina curentă / Înainte; de la `sm`: și numerele paginilor.
--}}
@php
    $item = 'inline-flex min-w-10 items-center justify-center rounded-control px-3 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2';
    $link = $item.' border border-line-strong bg-surface text-ink hover:bg-canvas';
    $disabled = $item.' cursor-not-allowed border border-line bg-surface text-ink-muted/60';
@endphp

@if ($paginator->hasPages())
    <nav aria-label="Paginare" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-muted">
            @if ($paginator->firstItem())
                <span class="font-medium text-ink">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> din
                <span class="font-medium text-ink">{{ $paginator->total() }}</span>
            @else
                {{ $paginator->count() }} din {{ $paginator->total() }}
            @endif
        </p>

        <ul class="flex flex-wrap items-center gap-1.5">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $disabled }}" aria-disabled="true">← Înapoi</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}">← Înapoi</a>
                @endif
            </li>

            <li class="sm:hidden">
                <span class="{{ $item }} text-ink-muted">Pagina {{ $paginator->currentPage() }} din {{ $paginator->lastPage() }}</span>
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="hidden sm:block"><span class="{{ $item }} text-ink-muted" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="hidden sm:block">
                            @if ($page == $paginator->currentPage())
                                <span class="{{ $item }} bg-inverse text-inverse-fg" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $link }}" aria-label="Pagina {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}">Înainte →</a>
                @else
                    <span class="{{ $disabled }}" aria-disabled="true">Înainte →</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
