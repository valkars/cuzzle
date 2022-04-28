<?php

namespace Middleware;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use Namshi\Cuzzle\Middleware\CurlFormatterMiddleware;

class CurlFormatterMiddlewareTest extends \PHPUnit\Framework\TestCase
{
    public function testGet(): void
    {
        $mock = new MockHandler([new Response(204)]);
        $handler = HandlerStack::create($mock);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $logger
            ->expects($this->once())
            ->method('debug')
            ->with($this->stringStartsWith('curl'));

        $handler->after('cookies', new CurlFormatterMiddleware($logger));
        $client = new Client(['handler' => $handler]);

        $client->get('https://google.com');
    }
}
