<?php

require_once 'Constants.php';

class HttpClient {
    private $region;
    private $email;
    private $phone;
    private $password;
    private $token;
    private $refreshToken;
    private $apikey;
    private $credentials;
    private $sign;
    private $baseUrl;
    private $devices; // Added property to store devices

    public function __construct() {
        $this->password = Constants::PASSWORD;
        $this->region = Constants::REGION;
        $this->email = Constants::EMAIL;
        $this->phone = Constants::PHONE_NUMBER;
        $this->token = null;
        $this->refreshToken = null;
        $this->apikey = null;
        $this->credentials = null;
        $this->sign = null;
        $this->baseUrl = "https://{$this->region}-api.coolkit.cc:8080/api";
        $this->devices = []; // Initialize the devices array

        $this->prepareConnection();
    }

    private function prepareConnection() {
        if ($this->region === 'cn') {
            if (empty($this->phone)) {
                throw new Exception("Phone number is required for region 'cn'");
            }
            $this->email = null; // Email is not necessary for region 'cn'
        } elseif ($this->region === 'us' || $this->region === 'eu') {
            if (empty($this->email)) {
                throw new Exception("Email is required for region 'us' or 'eu'");
            }
            $this->phone = null; // Phone is not necessary for region 'us' or 'eu'
        } else {
            throw new Exception("Unsupported region '{$this->region}'");
        }
    }

    private function createSignature($payload) {
        $secret = Constants::APP_SECRET;
        $data = json_encode($payload);
        $hmac = hash_hmac('sha256', $data, $secret, true);
        return base64_encode($hmac);
    }

    public function login() {
        $this->credentials = [
            'appid' => Constants::APP_ID,
            'password' => $this->password,
            'ts' => time(),
            'version' => 6,
            'nonce' => $this->generateNonce(),
            'os' => 'iOS',
            'model' => Constants::DEVICE_MODEL,
            'romVersion' => Constants::ROM_VERSION,
            'appVersion' => Constants::APP_VERSION,
            'imei' => $this->generateUUID()
        ];

        if ($this->region === 'cn') {
            $this->credentials['phoneNumber'] = $this->phone;
        } else {
            $this->credentials['email'] = $this->email;
        }

        $this->sign = $this->createSignature($this->credentials);
        $response = $this->postRequest($this->baseUrl . '/user/login', $this->credentials, "Sign {$this->sign}");

        if (isset($response['error']) && $response['error'] !== 0) {
            if (isset($response['region'])) {
                $this->region = $response['region'];
                $this->baseUrl = "https://{$this->region}-api.coolkit.cc:8080/api";
                return $this->login();
            } else {
                $this->handleError($response['error']);
            }
        } else {
            $this->token = $response['at'];
            $this->refreshToken = $response['rt'];
            $this->apikey = $response['user']['apikey'];
            return $response['user'];
        }
    }

    public function getDevices() {
        $url = $this->baseUrl . '/user/device';
        $params = [
            'lang' => 'en',
            'appid' => Constants::APP_ID,
            'ts' => time(),
            'version' => 8,
            'getTags' => 1
        ];

        $response = $this->getRequest($url, $params);
        if (isset($response['error']) && $response['error'] === 0 && isset($response['devicelist'])) {
            foreach ($response['devicelist'] as $device) {
                $this->devices[$device['name']] = $device['deviceid'];
            }
        } else {
            $this->handleError($response['error']);
        }

        return $this->devices;
    }

    public function getGateway() {
        $url = $this->region === 'cn' ? 'https://cn-apia.coolkit.cn' : "https://{$this->region}-dispa.coolkit.cc/dispatch/app";
        $response = $this->getRequest($url, []);
        return $response;
    }

    private function postRequest($url, $payload, $authorization) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: {$authorization}"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);
        if (isset($responseData['error']) && $responseData['error'] !== 0) {
            $this->handleError($responseData['error']);
        }

        return $responseData;
    }

    private function getRequest($url, $params) {
        $url .= '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->token}"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);
        if (isset($responseData['error']) && $responseData['error'] !== 0) {
            $this->handleError($responseData['error']);
        }

        return $responseData;
    }

    private function generateNonce() {
        return bin2hex(random_bytes(16));
    }

    private function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function handleError($errorCode) {
        if (isset(Constants::ERROR_CODES[$errorCode])) {
            throw new Exception(Constants::ERROR_CODES[$errorCode]);
        } else {
            throw new Exception('Unknown error occurred.');
        }
    }
}
?>
