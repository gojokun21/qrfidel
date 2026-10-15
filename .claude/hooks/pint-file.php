<?php

/**
 * Hook PostToolUse (Edit|Write): formatează cu Pint fișierul .php tocmai modificat.
 * Nu blochează niciodată: orice problemă iese cu 0.
 */
$root = dirname(__DIR__, 2);
$input = json_decode((string) stream_get_contents(STDIN), true);
$file = $input['tool_input']['file_path'] ?? $input['tool_response']['filePath'] ?? null;

// Blade-ul nu e formatat de Pint în restul proiectului (pint --test îl ignoră), deci nici aici.
if (! is_string($file) || ! str_ends_with($file, '.php') || str_ends_with($file, '.blade.php') || ! is_file($file)) {
    exit(0);
}

$real = (string) realpath($file);
$normalizedRoot = strtolower(str_replace('\\', '/', $root)).'/';
$normalizedFile = strtolower(str_replace('\\', '/', $real));

if (! str_starts_with($normalizedFile, $normalizedRoot) || str_contains($normalizedFile, '/vendor/')) {
    exit(0);
}

$pint = $root.'/vendor/bin/pint';
if (! is_file($pint)) {
    exit(0);
}

exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($pint).' --quiet '.escapeshellarg($real));

exit(0);
