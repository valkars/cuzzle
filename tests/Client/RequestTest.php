<?php

namespace Client;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Namshi\Cuzzle\Formatter\CurlFormatter;

class RequestTest extends \PHPUnit\Framework\TestCase
{
    protected CurlFormatter $curlFormatter;

    public function setUp(): void
    {
        $this->client = new Client();
        $this->curlFormatter = new CurlFormatter();
    }

    public function testGetWithCookies(): void
    {
        $request = new Request('GET', 'https://local.example');
        $jar = CookieJar::fromArray(['Foo' => 'Bar', 'identity' => 'xyz'], 'local.example');
        $curl = $this->curlFormatter->format($request, ['cookies' => $jar]);

        $this->assertStringNotContainsString("-H 'Host: local.example'", $curl);
        $this->assertStringContainsString("-b 'Foo=Bar; identity=xyz'", $curl);
    }

    public function testPOST(): void
    {
        $request = new Request('POST', 'https://local.example', [], Utils::streamFor('foo=bar&hello=world'));
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString("-d 'foo=bar&hello=world'", $curl);
    }

    public function testPUT(): void
    {
        $request = new Request('PUT', 'https://local.example', [], Utils::streamFor('foo=bar&hello=world'));
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString("-d 'foo=bar&hello=world'", $curl);
        $this->assertStringContainsString('-X PUT', $curl);
    }

    public function testDELETE(): void
    {
        $request = new Request('DELETE', 'https://local.example');
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString('-X DELETE', $curl);
    }

    public function testHEAD(): void
    {
        $request = new Request('HEAD', 'https://local.example');
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString("curl 'https://local.example' --head", $curl);
    }

    public function testOPTIONS(): void
    {
        $request = new Request('OPTIONS', 'https://local.example');
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString('-X OPTIONS', $curl);
    }
}
