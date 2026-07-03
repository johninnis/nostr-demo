<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Infrastructure\Relay\DemoRelayConfig;
use App\Infrastructure\Relay\InMemoryEventStore;
use Innis\Nostr\Core\Infrastructure\Crypto\NativeRandomBytesGenerator;
use Innis\Nostr\Relay\Application\Service\InMemoryAuthenticationRegistry;
use Innis\Nostr\Relay\Application\Service\RelayPolicy;
use Innis\Nostr\Relay\Domain\Exception\ConnectionException;
use Innis\Nostr\Relay\Domain\ValueObject\RelayPolicyConfig;
use Innis\Nostr\Relay\Infrastructure\Http\StaticNip11InfoProvider;
use Innis\Nostr\Relay\Infrastructure\RateLimiting\StaticRateLimitPolicy;
use Innis\Nostr\Relay\Infrastructure\Server\RelayServerFactory;
use Psr\Log\NullLogger;

use function Amp\trapSignal;

$host = $argv[1] ?? '127.0.0.1';
$port = (int) ($argv[2] ?? 8080);
$adminPubkey = $argv[3] ?? null;

if (null === $adminPubkey || 64 !== strlen($adminPubkey)) {
    fprintf(STDERR, "Usage: php %s [host] [port] <admin-pubkey-hex>\n", $argv[0] ?? 'start-relay.php');
    fprintf(STDERR, "  Generate a keypair with: php bin/generate-keys.php\n");
    exit(1);
}

$config = new DemoRelayConfig($host, $port);
$eventStore = new InMemoryEventStore();
$authManager = new InMemoryAuthenticationRegistry(new NativeRandomBytesGenerator());
$logger = new NullLogger();

$policyConfig = RelayPolicyConfig::tryFromArray([
    'tenants' => [$adminPubkey],
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

$policy = new RelayPolicy($authManager, $logger, $policyConfig);

$factory = new RelayServerFactory(
    eventStore: $eventStore,
    policy: $policy,
    config: $config,
    rateLimitPolicy: new StaticRateLimitPolicy($config->getRateLimitConfig()),
    authManager: $authManager,
    logger: $logger,
    nip11InfoProvider: new StaticNip11InfoProvider($config->getRelayInfo()),
);
$relay = $factory->create();

printf("Starting Nostr relay on ws://%s:%d\n", $host, $port);
printf("Admin pubkey: %s\n", $adminPubkey);
printf("Tenants can submit any event, guests can read kind 0 and 1 from tenants\n");
printf("Press Ctrl+C to stop\n\n");

try {
    $relay->start();
    trapSignal([SIGINT, SIGTERM]);
    $relay->stop();
} catch (ConnectionException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
