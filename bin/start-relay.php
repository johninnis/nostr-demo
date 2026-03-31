<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Infrastructure\Relay\AuthRequiredPolicy;
use App\Infrastructure\Relay\DemoRelayConfig;
use App\Infrastructure\Relay\InMemoryEventStore;
use Innis\Nostr\Relay\Application\Service\AuthenticationManager;
use Innis\Nostr\Relay\Domain\Exception\ConnectionException;
use Innis\Nostr\Relay\Infrastructure\Server\RelayServerFactory;
use Psr\Log\NullLogger;

$host = $argv[1] ?? '127.0.0.1';
$port = (int) ($argv[2] ?? 8080);
$adminPubkey = $argv[3] ?? null;

if (null === $adminPubkey || 64 !== strlen($adminPubkey)) {
    fprintf(STDERR, "Usage: php %s [host] [port] <admin-pubkey-hex>\n", $argv[0]);
    fprintf(STDERR, "  Generate a keypair with: php bin/generate-keys.php\n");
    exit(1);
}

$config = new DemoRelayConfig($host, $port);
$eventStore = new InMemoryEventStore();
$authManager = new AuthenticationManager();
$policy = new AuthRequiredPolicy($authManager, $adminPubkey);
$logger = new NullLogger();

$factory = new RelayServerFactory($eventStore, $policy, $config, $authManager, $logger);
$relay = $factory->create();

printf("Starting Nostr relay on ws://%s:%d\n", $host, $port);
printf("Admin pubkey: %s\n", $adminPubkey);
printf("Policy: authenticated users can submit any event, unauthenticated users see only admin kind 1 events\n");
printf("Press Ctrl+C to stop\n\n");

try {
    $relay->start();
} catch (ConnectionException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
