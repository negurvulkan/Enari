<?php

declare(strict_types=1);

require_once __DIR__ . '/../cms/SimpleYamlParser.php';
require_once __DIR__ . '/../cms/I18nContentValidator.php';

/**
 * Parses cli flags.
 *
 * @return array<string, bool>
 */
function parse_cli_flags(array $arguments): array
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
 * Processes print issue group.
 */
function print_issue_group(string $title, array $issues, int $limit, bool $showAll): void
{
    if ($issues === array()) {
        return;
    }

    echo PHP_EOL . $title . PHP_EOL;
    $visibleIssues = $showAll ? $issues : array_slice($issues, 0, $limit);

    foreach ($visibleIssues as $issue) {
        $suffix = array();
        if (($issue['locale'] ?? '') !== '') {
            $suffix[] = 'locale=' . $issue['locale'];
        }
        if (($issue['translationKey'] ?? '') !== '') {
            $suffix[] = 'translation_key=' . $issue['translationKey'];
        }
        if (($issue['path'] ?? '') !== '') {
            $suffix[] = 'path=' . $issue['path'];
        }

        echo '- ' . $issue['message'];
        if ($suffix !== array()) {
            echo ' [' . implode(', ', $suffix) . ']';
        }
        echo PHP_EOL;
    }

    if (!$showAll && count($issues) > $limit) {
        echo '- ... und ' . (count($issues) - $limit) . ' weitere Eintraege' . PHP_EOL;
    }
}

$flags = parse_cli_flags(array_slice($argv, 1));
$jsonOutput = isset($flags['--json']);
$strict = isset($flags['--strict']) || isset($flags['--fail-on-warnings']);
$includeInfo = isset($flags['--info']);
$showAll = isset($flags['--all']);

$basePath = dirname(__DIR__);
$siteConfig = require $basePath . '/cms/site.config.php';

$validator = new I18nContentValidator($basePath, is_array($siteConfig) ? $siteConfig : array());
$report = $validator->validate($includeInfo);

$summary = is_array($report['summary'] ?? null) ? $report['summary'] : array();
$issues = is_array($report['issues'] ?? null) ? $report['issues'] : array();
$errors = array_values(array_filter($issues, static function (array $issue): bool {
    return ($issue['severity'] ?? '') === 'error';
}));
$warnings = array_values(array_filter($issues, static function (array $issue): bool {
    return ($issue['severity'] ?? '') === 'warning';
}));
$infos = array_values(array_filter($issues, static function (array $issue): bool {
    return ($issue['severity'] ?? '') === 'info';
}));

if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo 'I18n Content Validation' . PHP_EOL;
    echo 'Default-Locale: ' . (string) ($report['config']['defaultLocale'] ?? 'n/a') . PHP_EOL;
    echo 'Dokumente: ' . (int) ($summary['documents'] ?? 0) . PHP_EOL;
    echo 'Translation-Gruppen: ' . (int) ($summary['translatedGroups'] ?? 0) . PHP_EOL;
    echo 'Locale-lokale Dokumente: ' . (int) ($summary['localeLocalDocuments'] ?? 0) . PHP_EOL;
    echo 'Fehler: ' . (int) ($summary['errors'] ?? 0) . PHP_EOL;
    echo 'Warnungen: ' . (int) ($summary['warnings'] ?? 0) . PHP_EOL;
    if ($includeInfo) {
        echo 'Infos: ' . (int) ($summary['infos'] ?? 0) . PHP_EOL;
    }

    print_issue_group('Fehler', $errors, 20, $showAll);
    print_issue_group('Warnungen', $warnings, 20, $showAll);
    if ($includeInfo) {
        print_issue_group('Infos', $infos, 20, $showAll);
    }
}

$exitCode = count($errors) > 0 ? 1 : 0;
if ($strict && count($warnings) > 0) {
    $exitCode = 1;
}

exit($exitCode);
