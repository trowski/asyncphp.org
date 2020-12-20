<?php

namespace AsyncPHP;

require __DIR__ . "/vendor/autoload.php";

use Amp\ByteStream\ResourceOutputStream;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Status;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket;
use Amp\Socket\Server;
use AsyncPHP\Middleware\HeaderMiddleware;
use AsyncPHP\Middleware\OriginMiddleware;
use Monolog\Logger;
use function Amp\signal;

$context = (new Socket\BindContext)
    ->withTlsContext(
        (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate('certificate.pem', 'key.pem'))
            ->withMinimumVersion(STREAM_CRYPTO_METHOD_TLSv1_2_SERVER)
    );

$encrypted = \array_map(function (string $uri) use ($context): Server {
    return Server::listen($uri, $context);
}, ORIGIN_URIS);

// Redirect server on unencrypted ports

$unencrypted = \array_map(function (string $uri): Server {
    return Server::listen($uri);
}, REDIRECT_URIS);

// Switch to configured user after binding sockets.

if (!\posix_setuid(USER_ID)) {
    throw new \RuntimeException('Could not switch to user ' . USER_ID);
}

$logHandler = new StreamHandler(new ResourceOutputStream(STDOUT));
$logHandler->setFormatter(new ConsoleFormatter);
$logger = new Logger('server');
$logger->pushHandler($logHandler);

$requestHandler = new CallableRequestHandler(static function (Request $request): Response {
    return new Response(Status::OK, [
        "content-type" => "text/plain; charset=utf-8"
    ], "Hello, World!");
});

$requestHandler = Middleware\stack(
    $requestHandler,
    new OriginMiddleware(ORIGIN_SCHEME, ORIGIN_HOST, ORIGIN_PORT, ORIGIN_REGEXP),
    new HeaderMiddleware,
);

// Primary server on encrypted ports.

$server = new HttpServer($encrypted, $requestHandler, $logger);

// Redirect server on unencrypted ports

$requestHandler = new CallableRequestHandler(function (Request $request): Response {
    $uri = $request->getUri();
    return new Response(Status::PERMANENT_REDIRECT, [
        'Location' => (string) $uri->withScheme(ORIGIN_SCHEME)->withHost(ORIGIN_HOST)->withPort(ORIGIN_PORT),
    ]);
});

$requestHandler = Middleware\stack(
    $requestHandler,
    new OriginMiddleware(REDIRECT_ORIGIN_SCHEME, REDIRECT_ORIGIN_HOST, REDIRECT_ORIGIN_PORT, REDIRECT_ORIGIN_REGEXP)
);

$redirect = new HttpServer($unencrypted, $requestHandler, $logger);

// Start servers.

$server->start();
$redirect->start();

// Await SIGINT, SIGTERM, or SIGSTOP to be received.
$signal = signal(\SIGINT, \SIGTERM, \SIGSTOP);

$logger->info(\sprintf("Received signal %d, stopping HTTP server", $signal));

$redirect->stop();
$server->stop();
