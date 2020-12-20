<?php

namespace AsyncPHP\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;

class HeaderMiddleware implements Middleware
{
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        $response = $requestHandler->handleRequest($request);
        \assert($response instanceof Response, 'Responder must resolve to an instance of ' . Response::class);

        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->setHeader('X-XSS-Protection', '1; mode=block');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        //$response->setHeader('Strict-Transport-Security', 'max-age=31536000');
        $response->setHeader('Referrer-Policy', 'same-origin');

        if (!$response->hasHeader('Content-Security-Policy')) {
            $response->setHeader('Content-Security-Policy', \implode('; ', [
                "default-src 'self'",
                "connect-src 'self' wss:",
                "style-src 'self' 'unsafe-inline'",
                'block-all-mixed-content',
                "base-uri 'self'",
            ]));
        }

        return $response;
    }
}
