<?php

namespace AsyncPHP\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Status;

class OriginMiddleware implements Middleware
{
    private string $scheme;

    private string $host;

    private int $port;

    private string $regexp;

    public function __construct(string $scheme, string $host, int $port, ?string $regexp = null)
    {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->port = $port;

        if ($regexp === null) {
            $this->regexp = '/^(www\\.)?' . \str_replace(['.', '/'], ['\\.', '\\/'], $this->host) . '$/i';
        } else {
            $this->regexp = $regexp;
        }
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        $uri = $request->getUri();

        if (!\preg_match($this->regexp, $uri->getAuthority(), $matches) || \count($matches) > 1) {
            return new Response(
                Status::PERMANENT_REDIRECT,
                ['Location' => (string) $uri->withScheme($this->scheme)->withHost($this->host)->withPort($this->port)]
            );
        }

        return $requestHandler->handleRequest($request);
    }
}
