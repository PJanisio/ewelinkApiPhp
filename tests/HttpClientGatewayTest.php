<?php
declare(strict_types=1);

namespace pjanisio\ewelinkapiphp\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Just checks that the four regional REST endpoints answer *something*.
 */
final class HttpClientGatewayTest extends TestCase
{
    /** @dataProvider regionProvider */
    public function testRestGatewayIsReachable(string $region, string $url): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,   // HEAD request – no payload
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $ok   = curl_exec($ch);                               // bool|false
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        /* ── 1. Network-level problem → FAIL ───────────────────────────── */
        if ($ok === false || $code === 0) {
            $this->fail(sprintf(
                'cURL network error for %s (%s): %s',
                $region,
                $url,
                $err ?: 'timeout / DNS / TLS failure'
            ));
        }

        /* ── 2. Otherwise: assert that we did get *some* HTTP code ─────── */
        $this->assertGreaterThan(
            0,
            $code,
            sprintf('Unexpected zero HTTP status for %s (%s)', $region, $url)
        );
    }

    /** @return array<array{string,string}> */
    public static function regionProvider(): array
    {
        return [
            ['cn', 'https://cn-apia.coolkit.cn'],
            ['as', 'https://as-apia.coolkit.cc'],
            ['us', 'https://us-apia.coolkit.cc'],
            ['eu', 'https://eu-apia.coolkit.cc'],
        ];
    }
}
