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
    public function testResolverBuildsWsUrl(): void
    {
        // arrange
        $dummy = new DummyHttpClient([
            'domain' => 'eu-pconnect7.coolkit.cc',
            'port'   => 8080,
        ]);

        // act
        $ws = new WebSocketClient($dummy);

        // reflect protected props
        $urlProp  = new ReflectionProperty($ws, 'url');
        $hostProp = new ReflectionProperty($ws, 'host');
        $portProp = new ReflectionProperty($ws, 'port');
        $urlProp->setAccessible(true);
        $hostProp->setAccessible(true);
        $portProp->setAccessible(true);

        // assert
        $expectedHost = gethostbyname('eu-pconnect7.coolkit.cc');
        $expectedUrl  = sprintf('wss://%s:8080/api/ws', $expectedHost);

        $this->assertSame($expectedUrl, $urlProp->getValue($ws));
        $this->assertSame($expectedHost, $hostProp->getValue($ws));
        $this->assertSame(8080, $portProp->getValue($ws));
    }
}
