<?php

include_once __DIR__ . '/vendor/autoload.php';

function get_client($scopes) {
    $client = new Google_Client();
    $credentials_file = get_option('credentials-file');
    $client->setAuthConfig($credentials_file);
    foreach ($scopes as $scope) {
        $client->addScope($scope);
    }
    return $client;
}

?>
