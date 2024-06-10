<?php

require_once 'Constants.php';
require_once 'HttpClient.php';

try {
    $client = new HttpClient();
    $user = $client->login();
    
    $devices = $client->getDevices();
    $gateway = $client->getGateway();

    echo '<pre>';
    var_dump($user);
    var_dump($devices);
    var_dump($gateway);
    echo '</pre>';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

?>