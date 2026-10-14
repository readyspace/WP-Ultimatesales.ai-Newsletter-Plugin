<?php
// Constants require isolated processes; these tests never bootstrap a site.
if (($argv[2] ?? '') === '--case') {
    require __DIR__ . '/fixture.php';
    require $argv[1] . '/includes/config.php';
    $value = json_decode(base64_decode($argv[3]), true, 512, JSON_THROW_ON_ERROR);
    if ($value['defined']) define('RS_NEWSLETTER_HTTP_ISOLATION_ERROR_CODE', $value['value']);
    $ready = \CleverSpeed\Newsletter\Config::ready();
    echo json_encode(['ready'=>$ready, 'fingerprint'=>$ready ? \CleverSpeed\Newsletter\Config::fingerprint() : null,
        'baseline'=>hash('sha256', json_encode(\CleverSpeed\Newsletter\Config::all(), JSON_UNESCAPED_SLASHES))]);
    exit;
}
$tests = 0;
function check($ok, $label) { global $tests; ++$tests; if (!$ok) throw new RuntimeException('FAIL ' . $label); }
function configurationCase(string $plugin, bool $defined, mixed $value): array {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, $plugin, '--case', base64_encode(json_encode(compact('defined','value')))],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start isolated configuration test.');
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException($error);
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}
$default = configurationCase($argv[1], false, null);
check($default['ready'] && $default['fingerprint'] === $default['baseline'], 'default upgrade preserves existing verification fingerprint');
$enabled = configurationCase($argv[1], true, 'host_newsletter_network_blocked');
check($enabled['ready'] && $enabled['fingerprint'] !== $enabled['baseline'], 'explicit integration requires new acceptance');
$changed = configurationCase($argv[1], true, 'different_host_network_blocked');
check($changed['ready'] && $changed['fingerprint'] !== $enabled['fingerprint'], 'changing the selected host policy requires new acceptance');
foreach (['', false, 123, [], 'A_HOST_ERROR', 'x', 'host error', "host_error\n", 'http_request_failed', 'http_request_not_executed', 'http_api_debug', str_repeat('a', 101)] as $invalid) {
    check(!configurationCase($argv[1], true, $invalid)['ready'], 'invalid or generic transport errors fail closed');
}
echo "PASS $tests isolation configuration checks\n";
