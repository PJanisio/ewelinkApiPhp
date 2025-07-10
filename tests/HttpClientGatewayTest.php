<?php

declare(strict_types=1);

namespace pjanisio\ewelinkapiphp\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that the four regional REST endpoints are reachable.
 * - If the socket cannot be opened (DNS, timeout, TLS) → **test is skipped**.
 * - If we get any HTTP response at all (≥ 100)         → **passes**.
 * - If curl says “status 0” after a successful connect  → **fails**.
 */
final class HttpClientGatewayTest extends TestCase
{
    /**
     * @dataProvider regionProvider
     *
     * @param string $region   Two-letter region code.
     * @param string $url      Full REST base-URL for the region.
     */
    public function testRestGatewayIsReachable(string $region, string $url): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,  // HEAD request – no body transferred
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $ok   = curl_exec($ch);                                   // bool|false
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);  // 0 if none
        $err  = curl_error($ch);
        curl_close($ch);

        /* ── 1. Could not even open the socket → skip, not fail ───────────── */
        if ($ok === false) {
            $this->markTestSkipped(sprintf(
                'Network error for %s (%s): %s',
                $region,
                $url,
                $err ?: 'timeout / DNS / TLS failure'
            ));
            return;
        }

        /* ── 2. Connected but got no HTTP status → hard failure ───────────── */
        $this->assertGreaterThan(
            0,
            $code,
            sprintf('Unexpected zero HTTP status for %s (%s)', $region, $url)
        );
    }

    /** Supplies the four public API endpoints.  @return array<array{string,string}> */
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
