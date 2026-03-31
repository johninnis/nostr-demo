<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Innis\Nostr\Client\Domain\Exception\ConnectionException;
use Innis\Nostr\Client\Infrastructure\Factory\NostrClientFactory;
use Innis\Nostr\Core\Application\Port\EventHandlerInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\Service\EventValidationService;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Psr\Log\NullLogger;

use function Amp\delay;

$relayUrl = RelayUrl::fromString($argv[1] ?? 'ws://127.0.0.1:8080');

if (null === $relayUrl) {
    fprintf(STDERR, "Invalid relay URL: %s\n", $argv[1] ?? '');
    exit(1);
}

$authorFilter = null;
$searchTerm = null;

if (isset($argv[2])) {
    $authorKey = PublicKey::fromHex($argv[2]);
    if (null === $authorKey) {
        fprintf(STDERR, "Invalid public key hex: %s\n", $argv[2]);
        exit(1);
    }
    $authorFilter = [$authorKey->toHex()];
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

    if (null !== $authorFilter) {
        printf("Filtering by author: %s\n", $authorFilter[0]);
    }
    if (null !== $searchTerm) {
        printf("Searching for: %s\n", $searchTerm);
    }
    printf("\n");

    $filter = new Filter(
        authors: $authorFilter,
        kinds: [EventKind::TEXT_NOTE],
        limit: 50,
        search: $searchTerm,
    );

    $validationService = new EventValidationService();

    $handler = new class($validationService) implements EventHandlerInterface {
        public function __construct(
            private readonly EventValidationService $validationService,
        ) {
        }

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

        public function handleEose(SubscriptionId $subscriptionId): void
        {
            printf("--- End of stored events ---\n\n");
        }

        public function handleClosed(SubscriptionId $subscriptionId, string $message): void
        {
            printf("Subscription closed: %s\n", $message);
        }

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
