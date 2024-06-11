<?php

require_once 'HttpClient.php';

// Function to get query parameter
function getQueryParam($name) {
    return isset($_GET[$name]) ? $_GET[$name] : null;
}

$state = 'your_state_here';
$httpClient = new HttpClient($state);

// Check if code and region are set and not empty
$code = getQueryParam('code');
$region = getQueryParam('region');

if ($code && $region) {
    try {
        // Get the token using the provided code and region
        $tokenData = $httpClient->getToken();
        echo '<h1>Token Data</h1>';
        echo '<pre>' . print_r($tokenData, true) . '</pre>';
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    // Display the login URL link
    $loginUrl = $httpClient->getLoginUrl();
    echo '<a href="' . htmlspecialchars($loginUrl) . '">Login with OAuth</a>';
}
