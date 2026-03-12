<?php

declare(strict_types=1);

require_once __DIR__ . '/../cms/SimpleYamlParser.php';
require_once __DIR__ . '/../cms/SchemaRegistry.php';
require_once __DIR__ . '/../cms/ContentRepository.php';
require_once __DIR__ . '/../cms/ReleaseSmokeTester.php';

$basePath = dirname(__DIR__);
$siteConfig = require $basePath . '/cms/site.config.php';

$tester = new ReleaseSmokeTester($basePath, is_array($siteConfig) ? $siteConfig : array());
$report = $tester->run();

echo 'Release Smoke Test' . PHP_EOL;

foreach (($report['checks'] ?? array()) as $check) {
    $status = !empty($check['passed']) ? 'PASS' : 'FAIL';
    echo '[' . $status . '] ' . (string) ($check['name'] ?? 'check') . ' - ' . (string) ($check['details'] ?? '') . PHP_EOL;
}

if (!empty($report['errors'])) {
    foreach ($report['errors'] as $error) {
        echo '[FAIL] ' . $error . PHP_EOL;
    }
}

$summary = is_array($report['summary'] ?? null) ? $report['summary'] : array();
echo PHP_EOL . 'Bestanden: ' . (int) ($summary['passed'] ?? 0) . PHP_EOL;
echo 'Fehlgeschlagen: ' . (int) ($summary['failed'] ?? 0) . PHP_EOL;

exit(((int) ($summary['failed'] ?? 0) > 0 || !empty($report['errors'])) ? 1 : 0);
