{{--
    Tabel de date. Pe ecrane mici se derulează orizontal în interiorul cardului (nu ascundem coloane).
    Slotul `head` = celulele antetului (x-ui.table.th); slotul principal = rândurile (<tr> cu x-ui.table.td).
--}}
<div {{ $attributes->class('overflow-x-auto') }}>
    <table class="w-full text-sm">
        <thead class="border-b border-line bg-canvas text-left text-xs font-medium uppercase tracking-wide text-ink-muted">
            <tr>{{ $head }}</tr>
        </thead>
        <tbody class="divide-y divide-line">
            {{ $slot }}
        </tbody>
    </table>
</div>
