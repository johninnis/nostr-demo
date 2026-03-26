<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Infrastructure\Relay\DemoRelayConfig;
use App\Infrastructure\Relay\InMemoryEventStore;
use App\Infrastructure\Relay\OpenPolicy;
use Innis\Nostr\Relay\Domain\Exception\ConnectionException;
use Innis\Nostr\Relay\Infrastructure\Server\RelayServerFactory;
use Psr\Log\NullLogger;

$host = $argv[1] ?? '127.0.0.1';
$port = (int) ($argv[2] ?? 8080);

$config = new DemoRelayConfig($host, $port);
$eventStore = new InMemoryEventStore();
$policy = new OpenPolicy();
$logger = new NullLogger();

$factory = new RelayServerFactory($eventStore, $policy, $config, $logger);
$relay = $factory->create();

printf("Starting Nostr relay on ws://%s:%d\n", $host, $port);
printf("Press Ctrl+C to stop\n\n");

try {
    $relay->start();
} catch (ConnectionException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
