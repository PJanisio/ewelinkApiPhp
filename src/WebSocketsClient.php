<?php

/**
 * Class: ewelinkApiPhp
 * Author: Paweł 'Pavlus' Janisio
 * Website: https://github.com/AceExpert/ewelink-api-python
 * Dependencies: PHP 7.4+
 * Description: API connector for Sonoff / ewelink devices
 */

class WebSocketClient {
    private $socket;
    private $url;
    private $host;
    private $port;
    private $path;
    private $key;
    private $utils;
    private $hbInterval;
    private $pid;

    public function __construct($url) {
        $this->url = $url;
        $parts = parse_url($url);
        $this->host = gethostbyname($parts['host']); // Resolve the domain to IP
        $this->port = $parts['port'];
        $this->path = $parts['path'];
        $this->utils = new Utils();
    }

    public function connect() {
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $this->socket = stream_socket_client("tls://{$this->host}:{$this->port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if (!$this->socket) {
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
            throw new Exception("WebSocket handshake failed. Sec-WebSocket-Accept missing.");
        }

        $expectedAcceptKey = base64_encode(pack('H*', sha1($this->key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        if ($acceptKey !== $expectedAcceptKey) {
            throw new Exception("WebSocket handshake failed. Invalid Sec-WebSocket-Accept: $acceptKey. Expected: $expectedAcceptKey.");
        }

        return true;
    }

    public function send($data) {
        if (!$this->socket) {
            throw new Exception("No valid WebSocket connection.");
        }
        $encodedData = $this->hybi10Encode($data);
        $result = @fwrite($this->socket, $encodedData);
        $this->utils->debugLog(__CLASS__, __FUNCTION__, ['data' => $data], [], ['fwriteResult' => $result], debug_backtrace()[1]['class'], debug_backtrace()[1]['function'], $this->url);
        if ($result === false) {
            throw new Exception("Failed to send data over WebSocket.");
        }
    }

    public function receive() {
        if (!$this->socket) {
            throw new Exception("No valid WebSocket connection.");
        }
        $response = @fread($this->socket, 1500);
        $decodedResponse = $this->hybi10Decode($response);
        $this->utils->debugLog(__CLASS__, __FUNCTION__, [], [], ['response' => $this->utils->sanitizeString($response), 'decodedResponse' => $decodedResponse], debug_backtrace()[1]['class'], debug_backtrace()[1]['function'], $this->url);
        return $decodedResponse;
    }

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

    public function sendRequest($data) {
        try {
            $this->connect();
        } catch (Exception $e) {
            throw new Exception('Error during connection: ' . $e->getMessage());
        }
        $this->send(json_encode($data));
        $response = $this->receive();
        $this->startHeartbeat(json_decode($response, true)['config']['hbInterval']);
        return json_decode($response, true);
    }

    private function startHeartbeat($interval) {
        $this->hbInterval = $interval + 7; // Add 7 seconds as mentioned in the documentation
        $this->pid = pcntl_fork();

        if ($this->pid == -1) {
            throw new Exception("Could not fork process for heartbeat");
        } else if ($this->pid) {
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
}
?>
