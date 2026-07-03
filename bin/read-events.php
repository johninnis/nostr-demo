<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Innis\Nostr\Client\Domain\Exception\ConnectionException;
use Innis\Nostr\Client\Infrastructure\Factory\NostrClientFactory;
use Innis\Nostr\Core\Application\Port\EventHandlerInterface;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\EventValidator;
use Innis\Nostr\Core\Domain\Service\NipComplianceValidator;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use Psr\Log\NullLogger;

use function Amp\delay;

$relayUrl = RelayUrl::tryFromString($argv[1] ?? 'ws://127.0.0.1:8080');

if (null === $relayUrl) {
    fprintf(STDERR, "Invalid relay URL: %s\n", $argv[1] ?? '');
    exit(1);
}

$authorKey = null;
$searchTerm = null;

if (isset($argv[2])) {
    $authorKey = PublicKey::tryFromHex($argv[2]);
    if (null === $authorKey) {
        fprintf(STDERR, "Invalid public key hex: %s\n", $argv[2]);
        exit(1);
    }
}

if (isset($argv[3])) {
    $searchTerm = $argv[3];
}

printf("=== Nostr Read Events Demo ===\n\n");

$client = NostrClientFactory::create(new NullLogger());

try {
    printf("Connecting to %s...\n", (string) $relayUrl);
    $client->connect($relayUrl);
    printf("Connected\n\n");

    if (null !== $authorKey) {
        printf("Filtering by author: %s\n", $authorKey->toHex());
    }
    if (null !== $searchTerm) {
        printf("Searching for: %s\n", $searchTerm);
    }
    printf("\n");

    $filter = new Filter(
        authors: null !== $authorKey ? new PublicKeyCollection([$authorKey]) : null,
        kinds: EventKindCollection::fromInts([EventKind::TEXT_NOTE]),
        limit: 50,
        search: $searchTerm,
    );

    $signatureService = Secp256k1Signer::create();
    $validationService = new EventValidator($signatureService, new NipComplianceValidator($signatureService));

    $handler = new class($validationService) implements EventHandlerInterface {
        public function __construct(
            private readonly EventValidator $validationService,
        ) {
        }

        #[Override]
        public function handleEvent(Event $event, SubscriptionId $subscriptionId): void
        {
            printf("--- Event ---\n");
            printf("  ID:      %s\n", $event->getId()->toHex());
            printf("  Author:  %s\n", $event->getPubkey()->toHex());
            printf("  Kind:    %d\n", $event->getKind()->toInt());
            printf("  Created: %s\n", $event->getCreatedAt()->toDateTime()->format('Y-m-d H:i:s'));
            printf("  Content: %s\n", (string) $event->getContent());
            printf("  Valid:   %s\n\n", $this->validationService->isEventValid($event) ? 'yes' : 'no');
        }

        #[Override]
        public function handleEose(SubscriptionId $subscriptionId): void
        {
            printf("--- End of stored events ---\n\n");
        }

        #[Override]
        public function handleClosed(SubscriptionId $subscriptionId, string $message): void
        {
            printf("Subscription closed: %s\n", $message);
        }

        #[Override]
        public function handleNotice(RelayUrl $relayUrl, string $message): void
        {
            printf("Relay notice from %s: %s\n", (string) $relayUrl, $message);
        }
    };

    $subscriptionId = $client->subscribe($relayUrl, $filter, $handler);
    printf("Subscribed (id: %s)\n", (string) $subscriptionId);
    printf("Listening for 30 seconds...\n\n");

    delay(30);

    $client->unsubscribe($relayUrl, $subscriptionId);
    printf("Unsubscribed\n");

    $client->disconnect($relayUrl);
    printf("Disconnected\n");
} catch (ConnectionException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
