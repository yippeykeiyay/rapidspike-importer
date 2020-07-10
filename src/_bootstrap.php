<?php

try {
    // Autoloader
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        include __DIR__ . '/../vendor/autoload.php';
    } else if (file_exists(__DIR__ . '/../../../autoload.php')) {
        include __DIR__ . '/../../../autoload.php';
    } else {
        exit('Fail - missing autoloader');
    }

    // Organise the parameters
    $params = array();
    if ($argc > 0) {
        // All args written with equals in
        foreach ($argv as $arg) {
            if (strpos($arg, '=') !== false) {
                list($key, $value) = explode('=', $arg);
                $params[$key] = $value;
            }
        }
    }

    // Validate we have what we need
    if (!isset($params['url'], $params['public_key'], $params['private_key'])) {
        throw new Exception('Missing required parts. Required params: url, public_key and private_key.');
    }

    if (!isset($params['domain_list']) && !file_exists($params['domain_list'])) {
        throw new Exception('Missing a valid domain_list');
    }

    $domain_list = file_get_contents($params['domain_list']);
} catch (Exception $e) {
    echo 'Fail in _bootstrap.php', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
    echo json_encode($e, JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}
