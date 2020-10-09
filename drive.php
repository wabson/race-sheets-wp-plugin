<?php

include_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . '/google-client.php';

function get_drive_html($drive_id, $remove_styles=true) {
    $transient_key = 'drive_content_' . $drive_id;
    $content = get_transient($transient_key);
    if (!$content) {
        $client = get_client([Google_Service_Drive::DRIVE_READONLY]);
        $service = new Google_Service_Drive($client);
        $response = $service->files->get($drive_id, array('alt' => 'media'));
        $content = $response->getBody()->getContents();
        set_transient($transient_key, $content, 60);
    }
    $doc = new DOMDocument();
    $load_result = $doc->loadHTML($content);
    remove_tags_by_name($doc, 'script');
    if ($remove_styles) {
        remove_tags_by_name($doc, 'style');
    }
    return $doc->saveHTML(); 
}

function get_drive_file($drive_file_id) {
    $client = get_client([Google_Service_Drive::DRIVE_READONLY]);
    $service = new Google_Service_Drive($client);
    return $service->files->get($drive_file_id, array('fields'=>'name,modifiedTime'));
}

?>
