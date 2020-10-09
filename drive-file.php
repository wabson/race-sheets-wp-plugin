<?php

include_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . '/google-client.php';

function get_drive_file($drive_id) {
    $client = get_client(Google_Service_Drive::DRIVE_READONLY);
    $service = new Google_Service_Drive($client);
    $response = $service->files->get($drive_id);
    echo $response->getBody()->getModifiedTime();
}

get_drive_file($_GET['fileId']);

?>
