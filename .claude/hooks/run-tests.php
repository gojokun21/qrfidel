<?php

/**
 * Hook Stop: rulează `composer test` dacă există modificări necomise în cod.
 * La eșec iese cu 2, iar rezumatul ajunge la Claude ca să repare înainte de a se opri.
 */
$root = dirname(__DIR__, 2);
$input = json_decode((string) stream_get_contents(STDIN), true);

// A doua oprire consecutivă după un eșec raportat: nu bloca din nou (evită bucla).
if (! empty($input['stop_hook_active'])) {
    exit(0);
}

if (! is_dir($root.'/vendor')) {
    exit(0);
}

chdir($root);

$paths = 'app bootstrap config database routes tests resources/views composer.json phpunit.xml';
$changes = trim((string) shell_exec('git status --porcelain -- '.$paths.' 2>&1'));

if ($changes === '') {
    exit(0);
}

exec('composer test --no-ansi 2>&1', $output, $code);

if ($code === 0) {
    exit(0);
}

$tail = implode("\n", array_slice($output, -40));
fwrite(STDERR, "composer test a eșuat (exit {$code}). Repară testele înainte de a încheia.\n"
    ."Dacă eroarea e de conexiune, pornește serviciile: docker compose up -d\n\n{$tail}\n");

exit(2);
