<?php

namespace Formatter;

use GuzzleHttp\Psr7\Utils;
use Namshi\Cuzzle\Formatter\CurlFormatter;
use GuzzleHttp\Psr7\Request;

class CurlFormatterTest extends \PHPUnit\Framework\TestCase
{
    protected CurlFormatter $curlFormatter;

    public function setUp(): void
    {
        $this->curlFormatter = new CurlFormatter();
    }

    public function testMultiLineDisabled(): void
    {
        $this->curlFormatter->setCommandLineLength(10);

        $request = new Request('GET', 'https://example.local', ['foo' => 'bar']);
        $curl = $this->curlFormatter->format($request);

        $this->assertEquals(2, \substr_count($curl, "\n"));
    }

    public function testSkipHostInHeaders(): void
    {
        $request = new Request('GET', 'https://example.local');
        $curl = $this->curlFormatter->format($request);

        $this->assertEquals("curl 'https://example.local'", $curl);
    }

    public function testSimpleGET(): void
    {
        $request = new Request('GET', 'https://example.local');
        $curl = $this->curlFormatter->format($request);

        $this->assertEquals("curl 'https://example.local'", $curl);
    }

    public function testSimpleGETWithHeader(): void
    {
        $request = new Request('GET', 'https://example.local', ['foo' => 'bar']);
        $curl = $this->curlFormatter->format($request);

        $this->assertEquals("curl 'https://example.local' -H 'foo: bar'", $curl);
    }

    public function testSimpleGETWithMultipleHeader(): void
    {
        $request = new Request('GET', 'https://example.local', ['foo' => 'bar', 'Accept-Encoding' => 'gzip,deflate,sdch']);
        $curl = $this->curlFormatter->format($request);

        $this->assertEquals("curl 'https://example.local' -H 'foo: bar' -H 'Accept-Encoding: gzip,deflate,sdch'", $curl);
    }

    public function testGETWithQueryString(): void
    {
        $request = new Request('GET', 'https://example.local?foo=bar');
        $curl = $this->curlFormatter->format($request);

        $this->assertEquals("curl 'https://example.local?foo=bar'", $curl);

        $request = new Request('GET', 'https://example.local?foo=bar');
        $curl = $this->curlFormatter->format($request);

        $this->assertEquals("curl 'https://example.local?foo=bar'", $curl);

        $body = Utils::streamFor(\http_build_query(['foo' => 'bar', 'hello' => 'world'], '', '&'));

        $request = new Request('GET', 'https://example.local', [], $body);
        $curl = $this->curlFormatter->format($request);

        $this->assertEquals("curl 'https://example.local' -G  -d 'foo=bar&hello=world'", $curl);
    }

    public function testPOST(): void
    {
        $body = Utils::streamFor(\http_build_query(['foo' => 'bar', 'hello' => 'world'], '', '&'));

        $request = new Request('POST', 'https://example.local', [], $body);
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString("-d 'foo=bar&hello=world'", $curl);
        $this->assertStringNotContainsString(" -G ", $curl);
    }

    public function testHEAD(): void
    {
        $request = new Request('HEAD', 'https://example.local');
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString("--head", $curl);
    }

    public function testOPTIONS(): void
    {
        $request = new Request('OPTIONS', 'https://example.local');
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString("-X OPTIONS", $curl);
    }

    public function testDELETE(): void
    {
        $request = new Request('DELETE', 'https://example.local/users/4');
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString("-X DELETE", $curl);
    }

    public function testPUT(): void
    {
        $request = new Request('PUT', 'https://example.local', [], Utils::streamFor('foo=bar&hello=world'));
        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString("-d 'foo=bar&hello=world'", $curl);
        $this->assertStringContainsString("-X PUT", $curl);
    }

    public function testProperBodyReading(): void
    {
        $request = new Request('PUT', 'https://example.local', [], Utils::streamFor('foo=bar&hello=world'));
        $request->getBody()->getContents();

        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString("-d 'foo=bar&hello=world'", $curl);
        $this->assertStringContainsString("-X PUT", $curl);
    }

    /**
     * @dataProvider getHeadersAndBodyData
     */
    public function testExtractBodyArgument($headers, $body): void
    {
        // clean input of null bytes
        $body = \str_replace(\chr(0), '', $body);
        $request = new Request('POST', 'https://example.local', $headers, Utils::streamFor($body));

        $curl = $this->curlFormatter->format($request);

        $this->assertStringContainsString('foo=bar&hello=world', $curl);
    }

    public function getHeadersAndBodyData(): array
    {
        return [
            [
                ['X-Foo' => 'Bar'],
                \chr(0).'foo=bar&hello=world',
            ],
        ];
    }
}
