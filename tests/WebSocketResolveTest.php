<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use pjanisio\ewelinkapiphp\WebSocketClient;
use pjanisio\ewelinkapiphp\HttpClient;

/**
 * Fakes one GET call.
 */
final class DummyHttpClient extends HttpClient
{
    /** @var array<string, mixed> */
    private array $reply;

    public function __construct(array $dispatchReply)
    {
        $this->reply = $dispatchReply;
        parent::__construct(); //still runs validation
    }

    public function getRequest($endpoint, $params = [], $full = false): array
    {
        return $this->reply;
    }
}

final class WebSocketResolveTest extends TestCase
{
    public function testResolverBuildsWsUrl(): void
    {
        $dummy = new DummyHttpClient([
            'domain' => 'eu-pconnect7.coolkit.cc',
            'port'   => 8080,
        ]);

        $ws = new WebSocketClient($dummy);

        // the protected props are set through resolveWebSocketUrl()
        $refUrl  = new ReflectionProperty($ws, 'url');
        $refUrl->setAccessible(true);
        $refHost = new ReflectionProperty($ws, 'host');
        $refHost->setAccessible(true);
        $refPort = new ReflectionProperty($ws, 'port');
        $refPort->setAccessible(true);

        $this->assertSame(
            'wss://' . gethostbyname('eu-pconnect7.coolkit.cc') . ':8080/api/ws',
            $refUrl->getValue($ws)
        );
        $this->assertSame(gethostbyname('eu-pconnect7.coolkit.cc'), $refHost->getValue($ws));
        $this->assertSame(8080, $refPort->getValue($ws));
    }
}
