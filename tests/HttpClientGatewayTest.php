<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use pjanisio\ewelinkapiphp\HttpClient;

final class HttpClientGatewayTest extends TestCase
{
    /** @dataProvider regionProvider */
    public function testRestGatewayIsReachable(string $region, string $url): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertTrue($ok, "cURL could not connect to $url");
        $this->assertSame(200, $code, "Gateway $url did not return 200");
    }

    public static function regionProvider(): array
    {
        $c = new class extends HttpClient {
            public function exposeGatewayMap(): array
            {
                return [
                    'cn' => 'https://cn-apia.coolkit.cn',
                    'as' => 'https://as-apia.coolkit.cc',
                    'us' => 'https://us-apia.coolkit.cc',
                    'eu' => 'https://eu-apia.coolkit.cc',
                ];
            }
        };
        $out = [];
        foreach ($c->exposeGatewayMap() as $reg => $url) {
            $out[] = [$reg, $url];
        }
        return $out;
    }
}
