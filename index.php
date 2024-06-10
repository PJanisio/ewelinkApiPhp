<?php

require_once 'Constants.php'; // Include the Constants.php file
require_once 'HttpClient.php'; // Assuming the HttpClient class is in the same directory

try {
    $password = 'your_password'; // Replace with your actual password
    $email = 'your_email@example.com'; // Replace with your actual email
    $region = 'us'; // Replace with your actual region

    $client = new HttpClient($password, $email, null, $region);
    $user = $client->login();
    
    $devices = $client->getDevices();

    echo '<pre>';
    var_dump($user);
    var_dump($devices);
    echo '</pre>';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

?>
