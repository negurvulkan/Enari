<?php

declare(strict_types=1);

require_once __DIR__ . '/../cms/SiteConfigLoader.php';

$basePath = dirname(__DIR__);
$report = SiteConfigLoader::validate($basePath);

echo 'Config Validation' . PHP_EOL;
echo 'Konfiguration: cms/site.config.php' . PHP_EOL;
echo 'Vorlage: cms/site.config.sample.php' . PHP_EOL;

if (!empty($report['ok'])) {
    echo '[PASS] Die lokale Konfiguration ist vorhanden und valide.' . PHP_EOL;
    exit(0);
}

echo '[FAIL] Die lokale Konfiguration ist nicht einsatzbereit.' . PHP_EOL;
foreach (($report['errors'] ?? array()) as $error) {
    echo '- ' . $error . PHP_EOL;
}

exit(1);
