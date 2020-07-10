<?php

include '_bootstrap.php';

try {
    /*
     * Create a list of domains with a list of the desired paths inside
     */
    $arrList = [];
    foreach (explode(',', $domain_list) as $domain_raw) {
        $Domain = new \RapidSpike\Targets\Url("https://{$domain_raw}", false);
        $path = rtrim((empty($Domain->getPath()) ? '/' : $Domain->getPath()), '_');
        $label = $path === '/' ? 'Home' : ucwords(str_replace('-', ' ', str_replace('/', '', $path)));
        $arrList[$Domain->getHost()][$path] = $label;
    }

    echo PHP_EOL, count($arrList) . " websites to create", PHP_EOL;

    // print_r($arrList);exit;

    $arrErrors = [];

    /*
     * Now create the websites and monitors
     */
    $i = 1;
    foreach ($arrList as $host => $arrPaths) {
        echo "{$i}. {$host}", PHP_EOL;
        $domain_name = "https://{$host}";

        // Open a fresh API client
        $objClient = new \RapidSpike\API\Client($params['public_key'], $params['private_key'], $params['url']);

        /*
         * CREATE THE WEBSITE
         */
        try {
            usleep(500000);
            echo "  Creating...", PHP_EOL;

            // Build create website POST body
            $arrCreateBody = array(
                'label' => $host,
                'domain_name' => $domain_name,
                'monitor_testing_period' => 5
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
            $arrErrors[$domain_name]['website_creation'][] = $e->getMessage();
            sleep(1);
            continue;
        }

        /*
         * CREATE AN SSL EXPIRY MONITOR
         */
        echo "  Creating a SSL expiry monitor...", PHP_EOL;
        try {
            usleep(500000);

            $arrMonitorBody = ['website_uuid' => $website_uuid];

            $objCreateRes = $objClient->sslmonitors()->addJsonBody($arrMonitorBody)->via('post');

            if (!empty($objCreateRes->error_code)) {
                throw new Exception("Creating the SSL monitor failed: {$objCreateRes->message}");
            }

            echo "  UUID: {$objCreateRes->data->ssl_monitor->monitor->uuid}", PHP_EOL;
        } catch (Exception $e) {
            echo 'ERRORS', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
            $arrErrors[$domain_name]['ssl_creation'][] = $e->getMessage();
            sleep(3);
            continue;
        }

        /*
         * CREATE THE HTTP MONITORS
         */
        $j = 1;
        echo "  Creating HTTP monitors...", PHP_EOL;
        foreach ($arrPaths as $path => $label) {
            echo "    {$i}.{$j}. {$label}", PHP_EOL;

            try {
                usleep(500000);

                $arrMonitorBody = array(
                    'website_uuid' => $website_uuid,
                    'http_monitors' => [
                        [
                            'label' => $label,
                            'target' => $path,
                            'expected_http_code' => 200
                        ]
                    ]
                );

                $objCreateRes = $objClient->httpmonitors()->addJsonBody($arrMonitorBody)->via('post');

                if (!empty($objCreateRes->error_code)) {
                    throw new Exception("Creating the HTTP monitor failed: {$objCreateRes->message}");
                }

                echo "    UUID: {$objCreateRes->data->http_monitors[0]->uuid}", PHP_EOL;
            } catch (Exception $e) {
                echo 'ERRORS', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
                $arrErrors[$domain_name]['http_creation'][$path] = $e->getMessage();
                sleep(3);
                continue;
            }

            $j++;
        }

        $i++;
//        if ($i > 5) {
//            exit;
//        }
    }
} catch (Exception $e) {
    echo 'Fail', PHP_EOL, PHP_EOL;
    print_r($e->getTrace());
    echo json_encode($e, JSON_PRETTY_PRINT), PHP_EOL;
}

if (!empty($arrErrors)) {
    print_r($arrErrors);
}

echo PHP_EOL, 'Completed', PHP_EOL;
exit;