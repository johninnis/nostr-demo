<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\InternetAddress;
use App\Infrastructure\Relay\DemoRelayConfig;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Infrastructure\Crypto\NativeRandomBytesGenerator;
use Innis\Nostr\Relay\Application\Service\InMemoryAuthenticationRegistry;
use Innis\Nostr\Relay\Application\Service\RelayPolicy;
use Innis\Nostr\Relay\Domain\ValueObject\RelayPolicyConfig;
use Innis\Nostr\Relay\Infrastructure\EventStore\InMemoryEventStore;
use Innis\Nostr\Relay\Infrastructure\Http\StaticNip11InfoProvider;
use Innis\Nostr\Relay\Infrastructure\RateLimiting\StaticRateLimitPolicy;
use Innis\Nostr\Relay\Infrastructure\Server\RelayServerFactory;
use Psr\Log\NullLogger;

use function Amp\trapSignal;

$host = $argv[1] ?? '127.0.0.1';
$port = (int) ($argv[2] ?? 8080);
$adminPubkeyHex = $argv[3] ?? null;

if (null === $adminPubkeyHex) {
    fprintf(STDERR, "Usage: php %s [host] [port] <admin-pubkey-hex>\n", $argv[0] ?? 'start-relay.php');
    fprintf(STDERR, "  Generate a keypair with: php bin/generate-keys.php\n");
    exit(1);
}

$adminPubkey = PublicKey::tryFromHex($adminPubkeyHex);
if (null === $adminPubkey) {
    fprintf(STDERR, "Invalid admin public key hex: %s\n", $adminPubkeyHex);
    exit(1);
}

$config = DemoRelayConfig::tryFrom($host, $port);
if (null === $config) {
    fprintf(STDERR, "Invalid relay host or port: %s:%d\n", $host, $port);
    exit(1);
}

$eventStore = new InMemoryEventStore();
$authenticationRegistry = new InMemoryAuthenticationRegistry(new NativeRandomBytesGenerator());
$logger = new NullLogger();

$policyConfig = RelayPolicyConfig::tryFromArray([
    'tenants' => [$adminPubkey->toHex()],
    'guest' => [
        'read' => [
            ['kinds' => [0, 1], 'from' => 'tenants'],
        ],
    ],
]);
if (null === $policyConfig) {
    fprintf(STDERR, "Invalid relay policy configuration\n");
    exit(1);
}

$policy = new RelayPolicy($authenticationRegistry, $logger, $policyConfig);

$factory = new RelayServerFactory(
    eventStore: $eventStore,
    policy: $policy,
    config: $config,
    rateLimitPolicy: new StaticRateLimitPolicy($config->getRateLimitConfig()),
    authenticationRegistry: $authenticationRegistry,
    logger: $logger,
    nip11InfoProvider: new StaticNip11InfoProvider($config->getRelayInfo()),
);

// From nostr-relay 0.6 the host owns the HTTP server: it binds the address and drives the
// lifecycle, and the relay is a request handler mounted on it.
$httpServer = SocketHttpServer::createForDirectAccess($logger);
$httpServer->expose(new InternetAddress($config->getHost(), $config->getPort()));

$relay = $factory->create($httpServer);

try {
    $httpServer->start($relay->getRequestHandler(), new DefaultErrorHandler());

    printf("Starting Nostr relay on ws://%s:%d\n", $host, $port);
    printf("Admin pubkey: %s\n", $adminPubkey->toHex());
    printf("Tenants can submit any event, guests can read kind 0 and 1 from tenants\n");
    printf("Press Ctrl+C to stop\n\n");

    trapSignal([SIGINT, SIGTERM]);
    $httpServer->stop();
} catch (Throwable $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
