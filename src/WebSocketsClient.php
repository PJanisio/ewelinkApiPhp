<?php

/**
 * Class: WebSocketClient
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/AceExpert/ewelink-api-python
 * Dependencies: PHP 7.4+
 * Description: WebSocket client for Sonoff / ewelink devices
 */

require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/Constants.php';

class WebSocketClient {
    private $socket;
    private $url;
    private $host;
    private $port;
    private $path;
    private $key;
    private $utils;
    private $httpClient;
    private $hbInterval;
    private $pid;

    /**
     * Constructor for the WebSocketClient class.
     *
     * @param HttpClient $httpClient The HTTP client instance.
     */
    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
        $this->utils = new Utils();
        $this->resolveWebSocketUrl();
    }

    /**
     * Resolve WebSocket URL based on the region.
     *
     * @throws Exception If the region is invalid.
     */
    private function resolveWebSocketUrl() {
        $region = Constants::REGION;
        switch ($region) {
            case 'cn':
                $url = 'https://cn-dispa.coolkit.cn/dispatch/app';
                break;
            case 'us':
                $url = 'https://us-dispa.coolkit.cc/dispatch/app';
                break;
            case 'eu':
                $url = 'https://eu-dispa.coolkit.cc/dispatch/app';
                break;
            case 'as':
                $url = 'https://as-dispa.coolkit.cc/dispatch/app';
                break;
            default:
                $errorCode = 'INVALID_REGION'; // Example error code
                $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
                throw new Exception($errorMsg);
        }

        $emptyRequestResponse = $this->httpClient->getRequest($url, [], true);

        if (!$emptyRequestResponse || empty($emptyRequestResponse['domain']) || empty($emptyRequestResponse['port'])) {
            $errorCode = 'EMPTY_REQUEST_RESPONSE'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }

        $ip = gethostbyname($emptyRequestResponse['domain']);
        $this->url = 'wss://' . $ip . ':' . $emptyRequestResponse['port'] . '/api/ws';

        $parts = parse_url($this->url);
        $this->host = gethostbyname($parts['host']); // Resolve the domain to IP
        $this->port = $parts['port'];
        $this->path = $parts['path'];
    }

    /**
     * Connect to the WebSocket server.
     *
     * @return bool True if the connection is successful.
     * @throws Exception If the connection or handshake fails.
     */
    public function connect() {
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $this->socket = stream_socket_client("tls://{$this->host}:{$this->port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if (!$this->socket) {
            $errorCode = 'UNABLE_TO_CONNECT'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception("Unable to connect to websocket: $errstr ($errno)");
        }

        $this->key = base64_encode(openssl_random_pseudo_bytes(16));
        $headers = [
            "GET $this->path HTTP/1.1",
            "Host: {$this->host}",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: $this->key",
            "Sec-WebSocket-Version: 13",
            "Sec-WebSocket-Protocol: chat",
            "Origin: null"  // Set Origin to null
        ];

        $request = implode("\r\n", $headers) . "\r\n\r\n";
        fwrite($this->socket, $request);

        $response = fread($this->socket, 1500);
        $this->utils->debugLog(__CLASS__, __FUNCTION__, [], $headers, ['response' => $response], debug_backtrace()[1]['class'], debug_backtrace()[1]['function'], $this->url);

        preg_match('#Sec-WebSocket-Accept:\s(.*)$#mUi', $response, $matches);
        $acceptKey = trim($matches[1] ?? '');

        if (!$acceptKey) {
            $errorCode = 'WS_HANDSHAKE_FAILED'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }

        $expectedAcceptKey = base64_encode(pack('H*', sha1($this->key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        if ($acceptKey !== $expectedAcceptKey) {
            $errorCode = 'INVALID_WS_ACCEPT'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }

        return true;
    }

    /**
     * Perform handshake over the WebSocket connection.
     *
     * @param array $device The device data.
     * @return array The response data.
     * @throws Exception If there is an error during the handshake process.
     */
    public function handshake($device) {
        $this->connect();
        $handshakeData = $this->createHandshakeData($device);
        $this->send(json_encode($handshakeData));
        $response = $this->receive();
        $responseData = json_decode($response, true);
        if (isset($responseData['config']['hbInterval'])) {
            $this->startHeartbeat($responseData['config']['hbInterval']);
        }
        return $responseData;
    }

    /**
     * Create handshake data for WebSocket connection.
     *
     * @param array $device The device data.
     * @return array The handshake data.
     */
    public function createHandshakeData($device) {
        $utils = new Utils();
        $tokenData = $this->httpClient->getTokenData();
        return [
            'action' => 'userOnline',
            'version' => 8,
            'ts' => time(),
            'at' => $tokenData['accessToken'],
            'userAgent' => 'app',
            'apikey' => $device['apikey'],
            'appid' => Constants::APPID,
            'nonce' => $utils->generateNonce(),
            'sequence' => strval(round(microtime(true) * 1000))
        ];
    }

    /**
     * Create query data for WebSocket connection.
     *
     * @param array $device The device data.
     * @param array|string $params The parameters to query.
     * @return array The query data.
     */
    public function createQueryData($device, $params) {
        return [
            'action' => 'query',
            'deviceid' => $device['deviceid'],
            'apikey' => $device['apikey'],
            'sequence' => strval(round(microtime(true) * 1000)),
            'params' => is_array($params) ? $params : [$params],
            'userAgent' => 'app'
        ];
    }

    /**
     * Create update data for WebSocket connection.
     *
     * @param array $device The device data.
     * @param array $params The parameters to update.
     * @param string $selfApikey The receiver's apikey.
     * @return array The update data.
     */
    public function createUpdateData($device, $params, $selfApikey) {
        return [
            'action' => 'update',
            'apikey' => $device['apikey'],
            'selfApikey' => $selfApikey,
            'deviceid' => $device['deviceid'],
            'params' => $params,
            'userAgent' => 'app',
            'sequence' => strval(round(microtime(true) * 1000))
        ];
    }

    /**
     * Send data over the WebSocket connection.
     *
     * @param string $data The data to send.
     * @throws Exception If there is no valid WebSocket connection or if sending the data fails.
     */
    public function send($data) {
        if (!$this->socket) {
            $errorCode = 'NO_VALID_WS_CONNECTION'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }
        $encodedData = $this->hybi10Encode($data);
        $result = @fwrite($this->socket, $encodedData);
        $this->utils->debugLog(__CLASS__, __FUNCTION__, ['data' => $data], [], ['fwriteResult' => $result], debug_backtrace()[1]['class'], debug_backtrace()[1]['function'], $this->url);
        if ($result === false) {
            $errorCode = 'FAILED_TO_SEND_WS_DATA'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }
    }

    /**
     * Receive data from the WebSocket connection.
     *
     * @return string The received data.
     * @throws Exception If there is no valid WebSocket connection.
     */
    public function receive() {
        if (!$this->socket) {
            $errorCode = 'NO_VALID_WS_CONNECTION'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }
        $response = @fread($this->socket, 1500);
        $decodedResponse = $this->hybi10Decode($response);
        $this->utils->debugLog(__CLASS__, __FUNCTION__, [], [], ['response' => $this->utils->sanitizeString($response), 'decodedResponse' => $decodedResponse], debug_backtrace()[1]['class'], debug_backtrace()[1]['function'], $this->url);
        return $decodedResponse;
    }

    /**
     * Close the WebSocket connection and stop the heartbeat process.
     */
    public function close() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
        if ($this->pid) {
            posix_kill($this->pid, SIGTERM);
            $this->pid = null;
            $this->utils->debugLog(__CLASS__, __FUNCTION__, [], [], ['message' => 'Heartbeat process stopped'], debug_backtrace()[1]['class'], debug_backtrace()[1]['function'], $this->url);
        }
    }

    /**
     * Encode data for sending over a WebSocket connection (hybi10 protocol).
     *
     * @param string $payload The data to encode.
     * @param string $type The type of data (default is 'text').
     * @param bool $masked Whether to mask the data (default is true).
     * @return string The encoded data.
     */
    private function hybi10Encode($payload, $type = 'text', $masked = true) {
        $frameHead = [];
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'text':
                $frameHead[0] = 129;
                break;
            case 'close':
                $frameHead[0] = 136;
                break;
            case 'ping':
                $frameHead[0] = 137;
                break;
            case 'pong':
                $frameHead[0] = 138;
                break;
        }

        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }

            if ($frameHead[2] > 127) {
                return false;
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }

        if ($masked === true) {
            $mask = [];
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    /**
     * Decode data received from a WebSocket connection (hybi10 protocol).
     *
     * @param string $data The data to decode.
     * @return string The decoded data.
     */
    private function hybi10Decode($data) {
        $bytes = $data;
        $dataLength = '';
        $mask = '';
        $codedData = '';
        $decodedData = '';
        $secondByte = sprintf('%08b', ord($bytes[1]));
        $masked = ($secondByte[0] == '1') ? true : false;
        $dataLength = ($masked === true) ? ord($bytes[1]) & 127 : ord($bytes[1]);

        if ($masked === true) {
            if ($dataLength === 126) {
                $mask = substr($bytes, 4, 4);
                $codedData = substr($bytes, 8);
            } elseif ($dataLength === 127) {
                $mask = substr($bytes, 10, 4);
                $codedData = substr($bytes, 14);
            } else {
                $mask = substr($bytes, 2, 4);
                $codedData = substr($bytes, 6);
            }
            for ($i = 0; $i < strlen($codedData); $i++) {
                $decodedData .= $codedData[$i] ^ $mask[$i % 4];
            }
        } else {
            if ($dataLength === 126) {
                $decodedData = substr($bytes, 4);
            } elseif ($dataLength === 127) {
                $decodedData = substr($bytes, 10);
            } else {
                $decodedData = substr($bytes, 2);
            }
        }

        return $decodedData;
    }

    /**
     * Start the heartbeat process to keep the WebSocket connection alive.
     *
     * @param int $interval The heartbeat interval in seconds.
     * @throws Exception If forking the process fails.
     */
    private function startHeartbeat($interval) {
        $this->hbInterval = $interval + 7; // Add 7 seconds as mentioned in the documentation
        $this->pid = pcntl_fork();

        if ($this->pid == -1) {
            $errorCode = 'FORK_FAILED'; // Example error code
            $errorMsg = Constants::ERROR_CODES[$errorCode] ?? 'Unknown error';
            throw new Exception($errorMsg);
        } elseif ($this->pid) {
            // Parent process
            return;
        } else {
            // Child process
            while (true) {
                sleep($this->hbInterval);
                try {
                    $this->send('ping');
                } catch (Exception $e) {
                    $this->utils->debugLog(__CLASS__, __FUNCTION__, [], [], ['message' => 'Failed to send ping', 'error' => $e->getMessage()], debug_backtrace()[1]['class'], debug_backtrace()[1]['function'], $this->url);
                    exit; // Exit child process if send fails
                }
            }
        }
    }

    /**
     * Get the WebSocket URL.
     *
     * @return string The WebSocket URL.
     */
    public function getWebSocketUrl() {
        return $this->url;
    }

    /**
     * Get the current Websocket connection
     *
     * @return bool|resource
     */
    public function getSocket() {
        return $this->socket;
    }
}
