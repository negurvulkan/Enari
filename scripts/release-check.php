<?php

declare(strict_types=1);

require_once __DIR__ . '/../cms/SimpleYamlParser.php';
require_once __DIR__ . '/../cms/I18nContentValidator.php';
require_once __DIR__ . '/../cms/SchemaRegistry.php';
require_once __DIR__ . '/../cms/ContentRepository.php';
require_once __DIR__ . '/../cms/ReleaseSmokeTester.php';
require_once __DIR__ . '/../cms/SiteConfigLoader.php';

/**
 * Processes release flags.
 *
 * @return array<string, bool>
 */
function release_flags(array $arguments): array
{
    $flags = array();
    foreach ($arguments as $argument) {
        if (strncmp($argument, '--', 2) !== 0) {
            continue;
        }

        $flags[$argument] = true;
    }

    return $flags;
}

/**
 * Processes release run command.
 *
 * @return array<string, mixed>
 */
function release_run_command(array $command, ?string $workingDirectory = null): array
{
    $spec = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );

    $pipes = array();
    $process = @proc_open($command, $spec, $pipes, $workingDirectory);
    if (!is_resource($process)) {
        return array(
            'exitCode' => 1,
            'stdout' => '',
            'stderr' => 'Befehl konnte nicht gestartet werden.',
        );
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return array(
        'exitCode' => is_int($exitCode) ? $exitCode : 1,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    );
}

/**
 * Processes release collect files.
 *
 * @return array<int, string>
 */
function release_collect_files(string $basePath, array $extensions, array $excludedPrefixes): array
{
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    /** @var SplFileInfo $fileInfo */
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $fullPath = str_replace('\\', '/', $fileInfo->getPathname());
        $relativePath = ltrim(substr($fullPath, strlen(str_replace('\\', '/', $basePath))), '/');
        $relativePath = str_replace('\\', '/', $relativePath);

        $isExcluded = false;
        foreach ($excludedPrefixes as $prefix) {
            if ($relativePath === $prefix || strpos($relativePath, $prefix . '/') === 0) {
                $isExcluded = true;
                break;
            }
        }

        if ($isExcluded) {
            continue;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $extensions, true)) {
            continue;
        }

        $files[] = $fullPath;
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    return $files;
}

/**
 * Processes release check php syntax.
 *
 * @return array<string, mixed>
 */
function release_check_php_syntax(string $basePath): array
{
    $files = release_collect_files($basePath, array('php'), array(
        '.git',
        '.codex_tmp',
        'cache',
        'cms/libs/smarty',
    ));

    $failures = array();
    foreach ($files as $file) {
        $result = release_run_command(array(PHP_BINARY, '-l', $file), $basePath);
        if (($result['exitCode'] ?? 1) !== 0) {
            $failures[] = array(
                'file' => $file,
                'message' => trim((string) ($result['stderr'] ?: $result['stdout'])),
            );
        }
    }

    return array(
        'files' => count($files),
        'failures' => $failures,
    );
}

/**
 * Processes release check JS syntax.
 *
 * @return array<string, mixed>
 */
function release_check_js_syntax(string $basePath): array
{
    $nodeVersion = release_run_command(array('node', '--version'), $basePath);
    if (($nodeVersion['exitCode'] ?? 1) !== 0) {
        return array(
            'available' => false,
            'files' => 0,
            'failures' => array(),
        );
    }

    $files = release_collect_files($basePath, array('js'), array(
        '.git',
        '.codex_tmp',
        'cache',
        'assets/vendor',
        'cms/libs/smarty',
    ));

    $failures = array();
    foreach ($files as $file) {
        $result = release_run_command(array('node', '--check', $file), $basePath);
        if (($result['exitCode'] ?? 1) !== 0) {
            $failures[] = array(
                'file' => $file,
                'message' => trim((string) ($result['stderr'] ?: $result['stdout'])),
            );
        }
    }

    return array(
        'available' => true,
        'files' => count($files),
        'failures' => $failures,
    );
}

/**
 * Processes release check admin editor fixtures.
 *
 * @return array<string, mixed>
 */
function release_check_admin_editor_fixtures(string $basePath): array
{
    $nodeVersion = release_run_command(array('node', '--version'), $basePath);
    if (($nodeVersion['exitCode'] ?? 1) !== 0) {
        return array(
            'available' => false,
            'exitCode' => 1,
            'output' => '',
        );
    }

    $scriptPath = $basePath . '/scripts/admin-editor-fixtures-check.js';
    if (!is_file($scriptPath)) {
        return array(
            'available' => true,
            'exitCode' => 1,
            'output' => 'Fixture-Script fehlt: ' . $scriptPath,
        );
    }

    $result = release_run_command(array('node', $scriptPath), $basePath);

    return array(
        'available' => true,
        'exitCode' => (int) ($result['exitCode'] ?? 1),
        'output' => trim((string) (($result['stdout'] ?? '') . PHP_EOL . ($result['stderr'] ?? ''))),
    );
}

$flags = release_flags(array_slice($argv, 1));
$strict = isset($flags['--strict']) || isset($flags['--fail-on-warnings']);
$includeInfo = isset($flags['--info']);

$basePath = dirname(__DIR__);
$hasFailures = false;

echo 'Release Check' . PHP_EOL;

$configReport = SiteConfigLoader::validate($basePath);
if (!empty($configReport['ok'])) {
    echo '[PASS] Config validation' . PHP_EOL;
    $siteConfig = SiteConfigLoader::load($basePath);
} else {
    echo '[FAIL] Config validation' . PHP_EOL;
    foreach (($configReport['errors'] ?? array()) as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

$phpSyntax = release_check_php_syntax($basePath);
if ($phpSyntax['failures'] === array()) {
    echo '[PASS] PHP syntax (' . $phpSyntax['files'] . ' Dateien)' . PHP_EOL;
} else {
    $hasFailures = true;
    echo '[FAIL] PHP syntax' . PHP_EOL;
    foreach ($phpSyntax['failures'] as $failure) {
        echo '- ' . $failure['file'] . ': ' . $failure['message'] . PHP_EOL;
    }
}

$jsSyntax = release_check_js_syntax($basePath);
if (!$jsSyntax['available']) {
    echo '[WARN] JS syntax wurde uebersprungen, weil `node` nicht verfuegbar ist.' . PHP_EOL;
} elseif ($jsSyntax['failures'] === array()) {
    echo '[PASS] JS syntax (' . $jsSyntax['files'] . ' Dateien)' . PHP_EOL;
} else {
    $hasFailures = true;
    echo '[FAIL] JS syntax' . PHP_EOL;
    foreach ($jsSyntax['failures'] as $failure) {
        echo '- ' . $failure['file'] . ': ' . $failure['message'] . PHP_EOL;
    }
}

$editorFixtures = release_check_admin_editor_fixtures($basePath);
if (!$editorFixtures['available']) {
    echo '[WARN] Admin editor fixtures wurden uebersprungen, weil `node` nicht verfuegbar ist.' . PHP_EOL;
} elseif ((int) ($editorFixtures['exitCode'] ?? 1) === 0) {
    echo '[PASS] Admin editor fixtures' . PHP_EOL;
} else {
    $hasFailures = true;
    echo '[FAIL] Admin editor fixtures' . PHP_EOL;
    if (($editorFixtures['output'] ?? '') !== '') {
        echo (string) $editorFixtures['output'] . PHP_EOL;
    }
}

$validator = new I18nContentValidator($basePath, is_array($siteConfig) ? $siteConfig : array());
$validationReport = $validator->validate($includeInfo);
$validationSummary = is_array($validationReport['summary'] ?? null) ? $validationReport['summary'] : array();
echo '[INFO] Content validation: '
    . (int) ($validationSummary['errors'] ?? 0) . ' Fehler, '
    . (int) ($validationSummary['warnings'] ?? 0) . ' Warnungen' . PHP_EOL;

if ((int) ($validationSummary['errors'] ?? 0) > 0 || ($strict && (int) ($validationSummary['warnings'] ?? 0) > 0)) {
    $hasFailures = true;
    foreach (($validationReport['issues'] ?? array()) as $issue) {
        if (($issue['severity'] ?? '') === 'info' && !$includeInfo) {
            continue;
        }
        if (($issue['severity'] ?? '') === 'warning' && !$strict) {
            continue;
        }

        echo '- [' . strtoupper((string) ($issue['severity'] ?? 'info')) . '] ' . $issue['message'];
        if (($issue['path'] ?? '') !== '') {
            echo ' [' . $issue['path'] . ']';
        }
        echo PHP_EOL;
    }
}

$smokeTester = new ReleaseSmokeTester($basePath, is_array($siteConfig) ? $siteConfig : array());
$smokeReport = $smokeTester->run();
$smokeSummary = is_array($smokeReport['summary'] ?? null) ? $smokeReport['summary'] : array();
if ((int) ($smokeSummary['failed'] ?? 0) === 0 && empty($smokeReport['errors'])) {
    echo '[PASS] Smoke test (' . (int) ($smokeSummary['passed'] ?? 0) . ' Checks)' . PHP_EOL;
} else {
    $hasFailures = true;
    echo '[FAIL] Smoke test' . PHP_EOL;
    foreach (($smokeReport['checks'] ?? array()) as $check) {
        if (!empty($check['passed'])) {
            continue;
        }

        echo '- ' . (string) ($check['name'] ?? 'check') . ': ' . (string) ($check['details'] ?? '') . PHP_EOL;
    }

    foreach (($smokeReport['errors'] ?? array()) as $error) {
        echo '- ' . $error . PHP_EOL;
    }
}

exit($hasFailures ? 1 : 0);
