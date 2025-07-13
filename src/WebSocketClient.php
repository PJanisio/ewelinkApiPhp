<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/PJanisio/ewelinkApiPhp
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

namespace pjanisio\ewelinkapiphp;

use Exception;

class WebSocketClient
{
    private $socket;
    private $url;
    private $host;
    private $port;
    private $path;
    private $key;
    private $utils;
    private $httpClient;
    private $hbInterval = 0;
    private $nextPing = 0.0;


    /**
     * Constructor for the WebSocketClient class.
     *
     * @param HttpClient $httpClient The HTTP client instance.
     */
    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->utils = new Utils();
        $this->resolveWebSocketUrl();
    }

    /**
     * Resolve WebSocket URL based on the region.
     *
     * @throws Exception If the region is invalid or the response is empty.
     */
    private function resolveWebSocketUrl()
    {
        $region = Constants::REGION;
        $urls = [
            'cn' => 'https://cn-dispa.coolkit.cn/dispatch/app',
            'us' => 'https://us-dispa.coolkit.cc/dispatch/app',
            'eu' => 'https://eu-dispa.coolkit.cc/dispatch/app',
            'as' => 'https://as-dispa.coolkit.cc/dispatch/app'
        ];

        if (!isset($urls[$region])) {
            throw new Exception(Constants::ERROR_CODES['INVALID_REGION'] ?? 'Unknown error');
        }

        $url = $urls[$region];
        $emptyRequestResponse = $this->httpClient->getRequest($url, [], true);

        if (!$emptyRequestResponse || empty($emptyRequestResponse['domain']) || empty($emptyRequestResponse['port'])) {
            throw new Exception(Constants::ERROR_CODES['EMPTY_REQUEST_RESPONSE'] ?? 'Unknown error');
        }

        $ip = gethostbyname($emptyRequestResponse['domain']);
        $this->url = 'wss://' . $ip . ':' . $emptyRequestResponse['port'] . '/api/ws';

        $parts = parse_url($this->url);
        $this->host = gethostbyname($parts['host']);
        $this->port = $parts['port'];
        $this->path = $parts['path'];
    }

    /**
     * Connect to the WebSocket server.
     *
     * @return bool True if the connection is successful.
     * @throws Exception If the connection or handshake fails.
     */
    public function connect()
    {
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $this->socket = stream_socket_client(
            "tls://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new Exception("Unable to connect to websocket: $errstr ($errno)");
        }

        $this->key = base64_encode(random_bytes(16));
        $headers = [
            "GET $this->path HTTP/1.1",
            "Host: {$this->host}",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: $this->key",
            "Sec-WebSocket-Version: 13",
            "Sec-WebSocket-Protocol: chat",
            "Origin: null"
        ];

        $request = implode("\r\n", $headers) . "\r\n\r\n";
        fwrite($this->socket, $request);

        $response = fread($this->socket, 1500);
        preg_match('#Sec-WebSocket-Accept:\s(.*)$#mUi', $response, $matches);
        $acceptKey = trim($matches[1] ?? '');

        if (!$acceptKey) {
            throw new Exception(Constants::ERROR_CODES['WS_HANDSHAKE_FAILED'] ?? 'Unknown error');
        }

        $expectedAcceptKey = base64_encode(pack('H*', sha1($this->key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        if ($acceptKey !== $expectedAcceptKey) {
            throw new Exception(Constants::ERROR_CODES['INVALID_WS_ACCEPT'] ?? 'Unknown error');
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
    public function handshake($device)
    {
        $this->connect();
        $handshakeData = $this->createHandshakeData($device);
        $this->send(json_encode($handshakeData));
        $response = $this->receive();
        $responseData = json_decode($response, true);

        if (isset($responseData['config']['hbInterval'])) {
            $this->hbInterval = $responseData['config']['hbInterval'] + 7;
            $this->nextPing   = microtime(true) + $this->hbInterval;
        }
        return $responseData;
    }

    /**
     * Create handshake data for WebSocket connection.
     *
     * @param array $device The device data.
     * @return array The handshake data.
     */
    public function createHandshakeData($device)
    {
        return [
            'action' => 'userOnline',
            'version' => 8,
            'ts' => time(),
            'at' => $this->httpClient->getToken()->getAccessToken(),
            'userAgent' => 'app',
            'apikey' => $device['apikey'],
            'appid' => Constants::APPID,
            'nonce' => $this->utils->generateNonce(),
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
    public function createQueryData($device, $params)
    {
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
    public function createUpdateData($device, $params, $selfApikey)
    {
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
    public function send(string $data = '', string $type = 'text')
    {
        $this->maybePing();
        if (!$this->socket) {
            throw new Exception(Constants::ERROR_CODES['NO_VALID_WS_CONNECTION'] ?? 'Unknown error');
        }
        $encoded = $this->hybi10Encode($data, $type, true);
        if (@fwrite($this->socket, $encoded) === false) {
            throw new Exception(Constants::ERROR_CODES['FAILED_TO_SEND_WS_DATA'] ?? 'Unknown error');
        }
    }

    /**
     * Receive data from the WebSocket connection.
     *
     * @return string The received data.
     * @throws Exception If there is no valid WebSocket connection.
     */
    public function receive()
    {
        $this->maybePing();
        if (!$this->socket) {
            throw new Exception(Constants::ERROR_CODES['NO_VALID_WS_CONNECTION'] ?? 'Unknown error');
        }
        $response = @fread($this->socket, 2048);
        return $this->hybi10Decode($response);
    }

    /**
     * Close the WebSocket connection and stop the ping process.
     */
    public function close()
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Encode data for sending over a WebSocket connection (HyBi-10).
     *
     * @param string $payload  Raw payload to send.
     * @param string $type     One of: text | close | ping | pong
     * @param bool   $masked   Whether to mask the frame (clients → server = true)
     *
     * @return string|false    Encoded frame, or false if payload > 2-bytes header
     */
    private function hybi10Encode(
        string $payload,
        string $type = 'text',
        bool $masked = true
    ) {
        $frameHead      = [];
        $mask           = [];
        $payloadLength  = strlen($payload);

        // 1️⃣  FIN-bit + opcode
        switch ($type) {
            case 'text':
                $frameHead[0] = 0b10000001;
                break; // 129
            case 'close':
                $frameHead[0] = 0b10001000;
                break; // 136
            case 'ping':
                $frameHead[0] = 0b10001001;
                break; // 137
            case 'pong':
                $frameHead[0] = 0b10001010;
                break; // 138
        }

        // 2️⃣  Payload length handling
        if ($payloadLength > 65535) {                 // > 64 KiB needs 8-byte ext len
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1]     = $masked ? 255 : 127;  // 8-byte length marker

            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }

            // RFC-6455 §5.2: MSB of 64-bit length MUST be 0
            if ($frameHead[2] > 127) {
                return false;                         // will satisfy PHPStan level-3
            }
        } elseif ($payloadLength > 125) {             // 126 … 65535 → 2-byte ext len
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1]     = $masked ? 254 : 126;  // 2-byte length marker
            $frameHead[2]     = bindec($payloadLengthBin[0]);
            $frameHead[3]     = bindec($payloadLengthBin[1]);
        } else {                                      // 0 … 125 → fits in 7 bits
            $frameHead[1] = $masked ? $payloadLength + 128 : $payloadLength;
        }

        // Convert all headers to raw bytes
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }

        // 3️⃣  Apply masking if requested
        if ($masked) {
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(random_int(0, 255));
            }
            $frameHead = array_merge($frameHead, $mask);
        }

        // 4️⃣  Assemble the frame
        $frame = implode('', $frameHead);
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= $masked
                ? ($payload[$i] ^ $mask[$i % 4])
                :  $payload[$i];
        }

        return $frame;
    }


    /**
     * Decode data received from a WebSocket connection (hybi10 protocol).
     *
     * @param string $data The data to decode.
     * @return string The decoded data.
     */
    private function hybi10Decode($data)
    {
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
     * Get the WebSocket URL.
     *
     * @return string The WebSocket URL.
     */
    public function getWebSocketUrl()
    {
        return $this->url;
    }

    /**
     * Get the current Websocket connection.
     *
     * @return bool|resource
     */
    public function getSocket()
    {
        return $this->socket;
    }


    /** Send a real WebSocket ping frame when it’s time. */
    private function maybePing()
    {
        if (
            $this->hbInterval &&
            microtime(true) >= $this->nextPing &&
            \is_resource($this->socket) &&
            !\feof($this->socket)
        ) {
            $frame = $this->hybi10Encode('', 'ping', true);
            @fwrite($this->socket, $frame);
            $this->nextPing = microtime(true) + $this->hbInterval;
        }
    }
}
