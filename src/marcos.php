<?php

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
    exit('Missing required parts. Required params: url, public_key and private_key.');
}

try {
    // Create an API client object
    $objClient = new RapidSpike\API\Client($params['public_key'], $params['private_key'], $params['url']);

    if (!isset($params['domain_list']) && !file_exists($params['domain_list'])) {
        throw new Exception('Missing a valid domain list');
    }

    // Load the domain list
    $domain_list = file_get_contents($params['domain_list']);
    $arrDomainList = explode(',', $domain_list);

    echo count($arrDomainList) . ' domains to import!', PHP_EOL;

    $monitor_testing_period = 5;
    $monitor_test_regions = ["us-west-1", "us-east-1"];

    foreach ($arrDomainList as $key => $domain_name) {
        // domain_name = store9061.marcos.com, label is the number
        $label = str_replace('.marcos.com', '', str_replace('store', '', $domain_name));

        // Add a schema
        $domain_name = "https://{$domain_name}";

        // Report some things
        $i = $key + 1;
        echo "#{$i}: {$domain_name}", PHP_EOL;
        echo "  Label: {$label}", PHP_EOL;
        echo "  Domain: {$domain_name}", PHP_EOL;

        /*
         * CREATE THE WEBSITE
         */
        try {
            echo "  Creating...", PHP_EOL;

            // Build create website POST body
            $arrCreateBody = array(
                'label' => $label,
                'domain_name' => $domain_name,
                'monitor_testing_period' => $monitor_testing_period
            );

            // Make the POST request to `/websites` - create the website
            $objCreateRes = $objClient->websites()
                    ->addJsonBody($arrCreateBody)
                    ->via('post');

            if (!empty($objCreateRes->error_code)) {
                throw new Exception("Creating the website failed: {$objCreateRes->message}");
            }

            // Extract the new website's UUID
            $website_uuid = $objCreateRes->data->website->uuid;
            echo "  UUID: {$website_uuid}", PHP_EOL;
        } catch (Exception $e) {
            echo 'ERRORS', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
            echo json_encode($e, JSON_PRETTY_PRINT), PHP_EOL;
            continue;
        }

        /*
         * UPDATE THE WEBSITE'S TEST REGIONS
         */
        try {
            echo "  Setting test regions...", PHP_EOL;

            // Update the website's test regions
            $objUpdateRes = $objClient->websites($website_uuid)
                    ->addJsonBody(['monitor_test_regions' => $monitor_test_regions])
                    ->via('put');

            if (!empty($objUpdateRes->error_code)) {
                throw new Exception("Updating the website failed: {$objUpdateRes->message}");
            }
        } catch (Exception $e) {
            echo 'ERRORS', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
            echo json_encode($e, JSON_PRETTY_PRINT), PHP_EOL;
            continue;
        }

        /*
         * CREATE THE HTTP MONITOR
         */
        try {
            echo "  Creating a HTTP monitor...", PHP_EOL;

            $arrMonitorBody = array(
                'website_uuid' => $website_uuid,
                'http_monitors' => [
                    [
                        'label' => 'Home',
                        'target' => '/',
                        'expected_http_code' => 200
                    ]
                ]
            );

            $objCreateRes = $objClient->httpmonitors()->addJsonBody($arrMonitorBody)->via('post');

            if (!empty($objCreateRes->error_code)) {
                throw new Exception("Creating the HTTP monitor failed: {$objCreateRes->message}");
            }
        } catch (Exception $e) {
            echo 'ERRORS', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
            echo json_encode($e, JSON_PRETTY_PRINT), PHP_EOL;
        }

        echo "  All good, moving on after 1 second.", PHP_EOL;
        sleep(1);
    }
} catch (\Exception $e) {
    echo 'Fail', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
    echo json_encode($e, JSON_PRETTY_PRINT), PHP_EOL;
}

echo PHP_EOL, 'Completed', PHP_EOL;
exit;
