<?php

/**
 * Internal request runner used by smoke tests to execute frontend requests in a clean PHP process.
 */

declare(strict_types=1);

/**
 * Internal request runner for release smoke tests.
 *
 * It simulates a single GET request against index.php in a dedicated PHP
 * process and emits a JSON payload with status code, body, and fatal error
 * details if the request crashes during shutdown.
 */

/**
 * Processes internal request parse arguments.
 *
 * @return array<string, mixed>
 */
function internal_request_parse_arguments(array $arguments): array
{
    $parsed = array(
        'uri' => '/',
        'cookies' => array(),
        'contains' => array(),
        'withBody' => false,
    );

    foreach ($arguments as $argument) {
        if (strpos($argument, '--uri=') === 0) {
            $parsed['uri'] = (string) substr($argument, 6);
            continue;
        }

        if (strpos($argument, '--cookie=') === 0) {
            $cookieDefinition = (string) substr($argument, 9);
            $separator = strpos($cookieDefinition, '=');
            if ($separator === false) {
                continue;
            }

            $name = trim(substr($cookieDefinition, 0, $separator));
            $value = substr($cookieDefinition, $separator + 1);
            if ($name === '') {
                continue;
            }

            $parsed['cookies'][$name] = urldecode($value);
            continue;
        }

        if (strpos($argument, '--contains=') === 0) {
            $needle = (string) substr($argument, 11);
            if ($needle === '') {
                continue;
            }

            $parsed['contains'][] = $needle;
            continue;
        }

        if ($argument === '--with-body') {
            $parsed['withBody'] = true;
        }
    }

    return $parsed;
}

/**
 * Processes internal request build server state.
 *
 * @return array<string, string>
 */
function internal_request_build_server_state(string $requestUri, array $cookies, string $basePath): array
{
    $queryString = (string) parse_url($requestUri, PHP_URL_QUERY);
    $cookieHeader = array();

    foreach ($cookies as $name => $value) {
        $cookieHeader[] = $name . '=' . $value;
    }

    return array(
        'DOCUMENT_ROOT' => $basePath,
        'GATEWAY_INTERFACE' => 'CGI/1.1',
        'HTTP_COOKIE' => implode('; ', $cookieHeader),
        'HTTP_HOST' => '127.0.0.1',
        'HTTPS' => 'off',
        'PATH_INFO' => (string) parse_url($requestUri, PHP_URL_PATH),
        'PHP_SELF' => '/index.php',
        'QUERY_STRING' => $queryString,
        'REDIRECT_STATUS' => '200',
        'REMOTE_ADDR' => '127.0.0.1',
        'REMOTE_PORT' => '58321',
        'REQUEST_METHOD' => 'GET',
        'REQUEST_TIME' => (string) time(),
        'REQUEST_TIME_FLOAT' => (string) microtime(true),
        'REQUEST_URI' => $requestUri,
        'SCRIPT_FILENAME' => $basePath . '/index.php',
        'SCRIPT_NAME' => '/index.php',
        'SERVER_ADDR' => '127.0.0.1',
        'SERVER_NAME' => '127.0.0.1',
        'SERVER_PORT' => '80',
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'SERVER_SOFTWARE' => 'Codex Internal Request Runner',
    );
}

/**
 * Processes internal request emit payload.
 */
function internal_request_emit_payload(): void
{
    static $emitted = false;

    if ($emitted) {
        return;
    }

    $emitted = true;
    $body = '';

    while (ob_get_level() > 0) {
        $body = (string) ob_get_clean() . $body;
    }

    $status = http_response_code();
    if (!is_int($status) || $status <= 0) {
        $status = 200;
    }

    $fatal = error_get_last();
    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);

    $payload = array(
        'status' => $status,
        'body' => !empty($GLOBALS['__internal_request_with_body']) ? $body : '',
        'contains' => array(),
        'fatal' => null,
    );

    $needles = is_array($GLOBALS['__internal_request_contains'] ?? null) ? $GLOBALS['__internal_request_contains'] : array();
    foreach ($needles as $needle) {
        if (!is_string($needle) || $needle === '') {
            continue;
        }

        $payload['contains'][$needle] = strpos($body, $needle) !== false;
    }

    if (is_array($fatal) && in_array((int) ($fatal['type'] ?? 0), $fatalTypes, true)) {
        $payload['fatal'] = array(
            'type' => (int) ($fatal['type'] ?? 0),
            'message' => (string) ($fatal['message'] ?? ''),
            'file' => (string) ($fatal['file'] ?? ''),
            'line' => (int) ($fatal['line'] ?? 0),
        );
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$options = internal_request_parse_arguments(array_slice($argv, 1));
$requestUri = trim((string) ($options['uri'] ?? ''));
$requestUri = $requestUri !== '' ? $requestUri : '/';
$cookies = is_array($options['cookies'] ?? null) ? $options['cookies'] : array();
$GLOBALS['__internal_request_contains'] = is_array($options['contains'] ?? null) ? $options['contains'] : array();
$GLOBALS['__internal_request_with_body'] = !empty($options['withBody']);
$basePath = str_replace('\\', '/', dirname(__DIR__));
$queryString = (string) parse_url($requestUri, PHP_URL_QUERY);

parse_str($queryString, $query);

$_GET = is_array($query) ? $query : array();
$_POST = array();
$_FILES = array();
$_COOKIE = $cookies;
$_REQUEST = array_replace($_GET, $_COOKIE);
$_SERVER = internal_request_build_server_state($requestUri, $cookies, $basePath);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);
header_remove();
http_response_code(200);

ob_start();
register_shutdown_function('internal_request_emit_payload');

require $basePath . '/index.php';

internal_request_emit_payload();
