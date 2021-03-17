<?php

namespace AsyncPHP;

require __DIR__ . "/vendor/autoload.php";

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\StaticContent\DocumentRoot;
use Amp\Http\Status;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Amp\Socket;
use Amp\Socket\Server;
use Amp\Websocket\Client;
use Amp\Websocket\Server\ClientHandler;
use Amp\Websocket\Server\Gateway;
use Amp\Websocket\Server\Websocket;
use AsyncPHP\Middleware\HeaderMiddleware;
use AsyncPHP\Middleware\OriginMiddleware;
use League\Uri\Uri;
use Monolog\Logger;
use function Amp\ByteStream\getStdout;
use function Amp\trap;

$context = (new Socket\BindContext)
    ->withTlsContext(
        (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate('certificate.pem', 'key.pem'))
            ->withMinimumVersion(STREAM_CRYPTO_METHOD_TLSv1_2_SERVER)
    );

// Main server on encrypted ports.
$encrypted = \array_map(function (string $uri) use ($context): Server {
    return Server::listen($uri, $context);
}, ORIGIN_URIS);

// Redirect server on unencrypted ports
$unencrypted = \array_map(function (string $uri): Server {
    return Server::listen($uri);
}, REDIRECT_URIS);

// Switch to configured user after binding sockets.
if (\defined('USER_ID') && !\posix_setuid(USER_ID)) {
    throw new \RuntimeException('Could not switch to user ' . USER_ID);
}

$logHandler = new StreamHandler(getStdout());
$logHandler->setFormatter(new ConsoleFormatter);
$logger = new Logger('server');
$logger->pushHandler($logHandler);

// Log any unexpected uncaught exceptions.
Loop::setErrorHandler(function (\Throwable $exception) use ($logger): void {
    $logger->alert('Uncaught exception from loop: ' . $exception->getMessage() . '; '
        . $exception->getTraceAsString());
});

$requestHandler = new Router;

$requestHandler->addRoute('GET', '/broadcast', new Websocket(new class() implements ClientHandler {
    private \SplQueue $messages;

    public function __construct()
    {
        $this->messages = new \SplQueue;
    }

    public function handleHandshake(Gateway $gateway, Request $request, Response $response): Response
    {
        $uri = Uri::createFromString($request->getHeader('origin'));

        if (!\preg_match(ORIGIN_REGEXP, $uri->getAuthority())) {
            return $gateway->getErrorHandler()->handleError(Status::FORBIDDEN, 'Origin forbidden', $request);
        }

        return $response;
    }

    public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): void
    {
        $this->sendQueuedMessages($client);

        $this->broadcast($gateway, \sprintf('Client %d joined', $client->getId()));

        try {
            while ($message = $client->receive()) {
                $this->broadcast($gateway, \sprintf('%d: %s', $client->getId(), $message->buffer()));
            }
        } finally {
            $this->broadcast($gateway, \sprintf('Client %d left', $client->getId()));
        }
    }

    private function broadcast(Gateway $gateway, string $payload): void
    {
        $this->messages->push($payload);

        if ($this->messages->count() > 20) {
            $this->messages->dequeue();
        }

        $gateway->broadcast($payload);
    }

    private function sendQueuedMessages(Client $client): void
    {
        foreach ($this->messages as $message) {
            $client->send($message);
        }
    }
}));

// Set static document request handler as router fallback.
$requestHandler->setFallback(new DocumentRoot(__DIR__ . '/public'));

// Stack middleware on request handler.
$requestHandler = Middleware\stack(
    $requestHandler,
    new OriginMiddleware(ORIGIN_SCHEME, ORIGIN_HOST, ORIGIN_PORT, ORIGIN_REGEXP),
    new HeaderMiddleware,
);

// Primary server on encrypted ports.
$server = new HttpServer($encrypted, $requestHandler, $logger);


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

// Redirect server on unencrypted ports
$redirect = new HttpServer($unencrypted, $requestHandler, $logger);

// Start servers.
$server->start();
$redirect->start();

// Await SIGINT, SIGTERM, or SIGSTOP to be received.
$signal = trap(\SIGINT, \SIGTERM);

$logger->info(\sprintf("Received signal %d, stopping HTTP server", $signal));

// Stop servers after signal is received.
$redirect->stop();
$server->stop();
