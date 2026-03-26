<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Innis\Nostr\Client\Domain\Exception\ConnectionException;
use Innis\Nostr\Client\Infrastructure\Factory\NostrClientFactory;
use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Psr\Log\NullLogger;

$relayUrl = RelayUrl::fromString($argv[1] ?? 'ws://127.0.0.1:8080');

if (null === $relayUrl) {
    fprintf(STDERR, "Invalid relay URL: %s\n", $argv[1] ?? '');
    exit(1);
}

printf("=== Nostr Publish Events Demo ===\n\n");

$keyPair = KeyPair::generate();
$privateKey = $keyPair->getPrivateKey();
$publicKey = $keyPair->getPublicKey();

printf("Generated identity:\n");
printf("  Public Key (hex):  %s\n", $publicKey->toHex());
printf("  Public Key (npub): %s\n\n", $publicKey->toBech32());

$client = NostrClientFactory::create(new NullLogger());

try {
    printf("Connecting to %s...\n", (string) $relayUrl);
    $client->connect($relayUrl);
    printf("Connected\n\n");

    $demoEvents = [
        EventFactory::createTextNote($publicKey, 'Hello Nostr! This is my first event from the demo.'),
        EventFactory::createTextNote($publicKey, 'Nostr is a simple, open protocol for decentralised social networking.', new TagCollection([
            Tag::hashtag('nostr'),
            Tag::hashtag('decentralised'),
        ])),
        EventFactory::createTextNote($publicKey, 'This demo showcases nostr-core, nostr-client, and nostr-relay working together.', new TagCollection([
            Tag::hashtag('demo'),
        ])),
    ];

    foreach ($demoEvents as $index => $unsignedEvent) {
        $number = $index + 1;
        $signedEvent = $unsignedEvent->sign($privateKey);
        $accepted = $client->publishEvent($relayUrl, $signedEvent);
        printf("Event %d: %s (id: %s)\n", $number, $accepted ? 'published' : 'rejected', $signedEvent->getId()->toHex());
    }

    printf("\nUse this pubkey to filter: %s\n\n", $publicKey->toHex());

    $client->disconnect($relayUrl);
    printf("Disconnected\n");
} catch (ConnectionException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
