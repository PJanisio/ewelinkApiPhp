<?php

require_once __DIR__ . '/src/HttpClient.php';
require_once __DIR__ . '/src/Constants.php';

// Function to get query parameter
function getQueryParam($name) {
    return isset($_GET[$name]) ? $_GET[$name] : null;
}

$state = 'your_state_here';
$httpClient = new HttpClient($state);

$code = getQueryParam('code');
$region = getQueryParam('region');

if ($code && $region) {
    try {
        $tokenData = $httpClient->getToken();
        echo '<h1>Token Data</h1>';
        echo '<pre>' . print_r($tokenData, true) . '</pre>';
        $httpClient->redirectToUrl(Constants::REDIRECT_URL);
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    if ($httpClient->checkAndRefreshToken()) {
        $tokenData = $httpClient->getTokenData();
        echo '<h1>Token is valid</h1>';
        echo '<p>Token expiry time: ' . date('Y-m-d H:i:s', $tokenData['atExpiredTime'] / 1000) . '</p>';

        try {
            $familyData = $httpClient->getFamilyData();
            echo '<h1>Family Data</h1>';
            echo '<pre>' . print_r($familyData, true) . '</pre>';
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        $loginUrl = $httpClient->getLoginUrl();
        echo '<a href="' . htmlspecialchars($loginUrl) . '">Login with OAuth</a>';
    }
}
