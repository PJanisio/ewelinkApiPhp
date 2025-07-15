<?php

declare(strict_types=1);

namespace pjanisio\ewelinkapiphp\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use pjanisio\ewelinkapiphp\HttpClient;
use pjanisio\ewelinkapiphp\WebSocketClient;

/**
 * Lightweight stub – fakes one GET call to the dispatch service so
 * we can test the URL-building logic without network access.
 */
final class DummyHttpClient extends HttpClient
{
    /** @var array<string,mixed> */
    private array $reply;

    public function __construct(array $dispatchReply)
    {
        $this->reply = $dispatchReply;
        parent::__construct();
    }

    /** @inheritDoc */
    public function getRequest($endpoint, $params = [], $useFullUrl = false): array
    {
        return $this->reply;
    }
}

final class WebSocketResolveTest extends TestCase
{
    /**
     * @dataProvider dispatchProvider
     */
    public function testResolverBuildsWsUrl(string $domain, int $port): void
    {
        $dummy = new DummyHttpClient([
            'domain' => $domain,
            'port' => $port,
        ]);


        $ws = new WebSocketClient($dummy);

        // Reach into the protected properties that were filled in
        // by resolveWebSocketUrl().
        $urlProp = new ReflectionProperty($ws, 'url');
        $hostProp = new ReflectionProperty($ws, 'host');
        $portProp = new ReflectionProperty($ws, 'port');
        $urlProp->setAccessible(true);
        $hostProp->setAccessible(true);
        $portProp->setAccessible(true);


        $expectedHost = gethostbyname($domain); // resolves to IP
        $expectedUrl = sprintf('wss://%s:%d/api/ws', $expectedHost, $port);

        $this->assertSame($expectedUrl, $urlProp->getValue($ws));
        $this->assertSame($expectedHost, $hostProp->getValue($ws));
        $this->assertSame($port, $portProp->getValue($ws));
    }

    /**
     * Supplies one “dispatch” reply per region.
     *
     * @return array<array{string,int}>
     */
    public static function dispatchProvider(): array
    {
        return [
            // domain, port
            ['cn-pconnect7.coolkit.cn', 8080],
            ['us-pconnect7.coolkit.cc', 8080],
            ['eu-pconnect7.coolkit.cc', 8080],
            ['as-pconnect7.coolkit.cc', 8080],
        ];
    }
}
