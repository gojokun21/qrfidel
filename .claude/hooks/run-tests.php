<?php

/**
 * Hook Stop: rulează `composer test` dacă codul s-a schimbat de la ultima rulare verde.
 * La eșec iese cu 2, iar rezumatul ajunge la Claude ca să repare înainte de a se opri.
 * După MAX_BLOCKS blocări consecutive lasă oprirea, dar avertizează utilizatorul.
 */
const MAX_BLOCKS = 3;
const DB_HOST = '127.0.0.1';
const DB_PORT = 3307;

$root = dirname(__DIR__, 2);
$input = json_decode((string) stream_get_contents(STDIN), true);
$sessionId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($input['session_id'] ?? 'default'));

if (! is_dir($root.'/vendor')) {
    exit(0);
}

chdir($root);

$stateDir = sys_get_temp_dir().'/creative-loyalty-hooks';
@mkdir($stateDir, 0777, true);
$greenFile = $stateDir.'/last-green.hash';
$blocksFile = $stateDir.'/blocks-'.$sessionId.'.count';

$paths = 'app bootstrap config database routes tests resources/views composer.json phpunit.xml';
$status = trim((string) shell_exec('git status --porcelain -- '.$paths.' 2>&1'));

if ($status === '') {
    @unlink($blocksFile);
    exit(0);
}

// Starea curentă a codului: diff față de HEAD + fișierele netrecute în git (cu conținut).
$fingerprint = (string) shell_exec('git diff HEAD -- '.$paths.' 2>&1');
foreach (array_filter(explode("\n", (string) shell_exec('git ls-files --others --exclude-standard -- '.$paths))) as $file) {
    $fingerprint .= $file.(is_file($file) ? sha1_file($file) : '');
}
$hash = sha1($fingerprint);

if (is_file($greenFile) && trim((string) file_get_contents($greenFile)) === $hash) {
    exit(0);
}

$block = function (string $message) use ($blocksFile): never {
    $count = (int) @file_get_contents($blocksFile) + 1;

    if ($count > MAX_BLOCKS) {
        @unlink($blocksFile);
        echo json_encode(['systemMessage' => 'Atenție: testele sunt încă roșii după '.MAX_BLOCKS.' încercări. Sesiunea NU e gata de închis.']);
        exit(0);
    }

    file_put_contents($blocksFile, (string) $count);
    fwrite(STDERR, $message);
    exit(2);
};

$socket = @fsockopen(DB_HOST, DB_PORT, $errno, $errstr, 2);
if ($socket === false) {
    $block('MySQL nu răspunde pe '.DB_HOST.':'.DB_PORT." – testele nu au rulat.\n"
        ."Nu e o problemă de cod: pornește Docker Desktop și rulează `docker compose up -d`, apoi reia.\n");
}
fclose($socket);

exec('composer test --no-ansi 2>&1', $output, $code);

if ($code === 0) {
    file_put_contents($greenFile, $hash);
    @unlink($blocksFile);
    exit(0);
}

$tail = (string) preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', implode("\n", array_slice($output, -40)));
$block("composer test a eșuat (exit {$code}). Repară testele înainte de a încheia.\n\n{$tail}\n");
