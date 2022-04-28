<?php

namespace Namshi\Cuzzle\Formatter;

use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Cookie\SetCookie;
use Psr\Http\Message\RequestInterface;

/**
 * Class CurlFormatter it formats a Guzzle request to a cURL shell command
 *
 * @package Namshi\Cuzzle\Formatter
 */
class CurlFormatter
{
    protected ?string $command = null;

    protected ?int $currentLineLength = null;

    /**
     * @var string[]
     */
    protected ?array $options = null;

    public function __construct(protected int $commandLineLength = 100)
    {
    }

    public function format(RequestInterface $request, array $options = []): string
    {
        $this->command = 'curl';
        $this->currentLineLength = strlen($this->command);
        $this->options = [];

        $this->extractArguments($request, $options);
        $this->addOptionsToCommand();

        return $this->command;
    }

    public function setCommandLineLength(int $commandLineLength): self
    {
        $this->commandLineLength = $commandLineLength;

        return $this;
    }

    protected function addOption($name, $value = null): void
    {
        if (isset($this->options[$name])) {
            if (!\is_array($this->options[$name])) {
                $this->options[$name] = (array) $this->options[$name];
            }

            $this->options[$name][] = $value;
        } else {
            $this->options[$name] = $value;
        }
    }

    protected function addCommandPart($part): void
    {
        $this->command .= ' ';

        if ($this->commandLineLength > 0 && $this->currentLineLength + \strlen($part) > $this->commandLineLength) {
            $this->currentLineLength = 0;
            $this->command .= "\\\n  ";
        }

        $this->command .= $part;
        $this->currentLineLength += \strlen($part) + 2;
    }

    protected function extractHttpMethodArgument(RequestInterface $request): void
    {
        if ('GET' !== $request->getMethod()) {
            if ('HEAD' === $request->getMethod()) {
                $this->addOption('-head');
            } else {
                $this->addOption('X', $request->getMethod());
            }
        }
    }

    protected function extractBodyArgument(RequestInterface $request): void
    {
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $previousPosition = $body->tell();
            $body->rewind();
        }

        $contents = $body->getContents();

        if ($body->isSeekable()) {
            $body->seek($previousPosition);
        }

        if ($contents) {
            // clean input of null bytes
            $contents = \str_replace(\chr(0), '', $contents);
            $this->addOption('d', \escapeshellarg($contents));
        }

        //if get request has data Add G otherwise curl will make a post request
        if (!empty($this->options['d']) && ('GET' === $request->getMethod())) {
            $this->addOption('G');
        }
    }

    protected function extractCookiesArgument(RequestInterface $request, array $options): void
    {
        if (!isset($options['cookies']) || !$options['cookies'] instanceof CookieJarInterface) {
            return;
        }

        $values = [];
        $scheme = $request->getUri()->getScheme();
        $host = $request->getUri()->getHost();
        $path = $request->getUri()->getPath();

        /** @var SetCookie $cookie */
        foreach ($options['cookies'] as $cookie) {
            if ($cookie->matchesPath($path) && $cookie->matchesDomain($host) &&
                !$cookie->isExpired() && (!$cookie->getSecure() || $scheme === 'https')) {

                $values[] = $cookie->getName().'='.$cookie->getValue();
            }
        }

        if ($values) {
            $this->addOption('b', \escapeshellarg(\implode('; ', $values)));
        }
    }

    protected function extractHeadersArgument(RequestInterface $request): void
    {
        foreach ($request->getHeaders() as $name => $header) {
            if ('host' === \strtolower($name) && $header[0] === $request->getUri()->getHost()) {
                continue;
            }

            if ('user-agent' === \strtolower($name)) {
                $this->addOption('A', \escapeshellarg($header[0]));
                continue;
            }

            foreach ((array) $header as $headerValue) {
                $this->addOption('H', \escapeshellarg("{$name}: {$headerValue}"));
            }
        }
    }

    protected function addOptionsToCommand(): void
    {
        \ksort($this->options);

        if ($this->options) {
            foreach ($this->options as $name => $value) {
                if (\is_array($value)) {
                    foreach ($value as $subValue) {
                        $this->addCommandPart("-{$name} {$subValue}");
                    }
                } else {
                    $this->addCommandPart("-{$name} {$value}");
                }
            }
        }
    }

    protected function extractArguments(RequestInterface $request, array $options): void
    {
        $this->extractHttpMethodArgument($request);
        $this->extractBodyArgument($request);
        $this->extractCookiesArgument($request, $options);
        $this->extractHeadersArgument($request);
        $this->extractUrlArgument($request);
    }

    protected function extractUrlArgument(RequestInterface $request): void
    {
        $this->addCommandPart(escapeshellarg((string) $request->getUri()->withFragment('')));
    }
}
