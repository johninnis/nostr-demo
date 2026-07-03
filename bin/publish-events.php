<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Infrastructure\Client\EventCollector;
use Innis\Nostr\Client\Application\Port\AuthChallengeHandlerInterface;
use Innis\Nostr\Client\Infrastructure\Factory\NostrClientFactory;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use Psr\Log\NullLogger;

use function Amp\delay;

$relayUrlString = $argv[1] ?? 'ws://127.0.0.1:8080';
$adminPrivateKeyHex = $argv[2] ?? null;

$relayUrl = RelayUrl::fromString($relayUrlString);
if (null === $relayUrl) {
    fprintf(STDERR, "Invalid relay URL: %s\n", $relayUrlString);
    exit(1);
}

if (null === $adminPrivateKeyHex) {
    fprintf(STDERR, "Usage: php %s [relay-url] <admin-private-key-hex>\n", $argv[0]);
    fprintf(STDERR, "  Generate a keypair with: php bin/generate-keys.php\n");
    exit(1);
}

$signatureService = Secp256k1Signer::create();

$adminKey = PrivateKey::fromHex($adminPrivateKeyHex);
if (null === $adminKey) {
    fprintf(STDERR, "Invalid private key hex\n");
    exit(1);
}
$adminKeyPair = KeyPair::fromPrivateKey($adminKey, $signatureService);
$adminPubkey = $adminKeyPair->getPublicKey();
$guestKeyPair = KeyPair::generate($signatureService);

printf("=== Nostr Demo: NIP-42 Auth, NIP-09 Deletion, NIP-50 Search ===\n\n");
printf("Admin pubkey:  %s\n", $adminPubkey->toHex());
printf("Guest pubkey:  %s\n\n", $guestKeyPair->getPublicKey()->toHex());

$authHandler = new class($adminKeyPair, $relayUrl, $signatureService) implements AuthChallengeHandlerInterface {
    public function __construct(
        private readonly KeyPair $keyPair,
        private readonly RelayUrl $relayUrl,
        private readonly SignatureServiceInterface $signatureService,
    ) {
    }

    #[Override]
    public function handleAuthChallenge(RelayUrl $relayUrl, string $challenge): ?Event
    {
        printf("  [AUTH] Challenge received, responding...\n");

        return RumourFactory::createAuth(
            $this->keyPair->getPublicKey(),
            $this->relayUrl,
            $challenge,
        )->sign($this->keyPair, $this->signatureService);
    }
};

$client = NostrClientFactory::create(new NullLogger());
$client->setAuthHandler($authHandler);

try {
    printf("--- Step 1: Connect and authenticate as admin ---\n");
    $client->connect($relayUrl);
    printf("  Connected to %s\n", (string) $relayUrl);

    $adminNote = RumourFactory::createTextNote(
        $adminPubkey,
        'Admin note: Welcome to the Nostr demo relay.',
    )->sign($adminKeyPair, $signatureService);
    $result = $client->publishEvent($relayUrl, $adminNote)->await();
    printf("  Admin event 1: %s (id: %s)\n", $result->isAccepted() ? 'accepted' : 'rejected', $adminNote->getId()->toHex());

    $adminSearchNote = RumourFactory::createTextNote(
        $adminPubkey,
        'Nostr is a decentralised protocol for social networking.',
        new TagCollection([
            Tag::hashtag('nostr'),
            Tag::hashtag('decentralised'),
        ]),
    )->sign($adminKeyPair, $signatureService);
    $result = $client->publishEvent($relayUrl, $adminSearchNote)->await();
    printf("  Admin event 2: %s (id: %s)\n", $result->isAccepted() ? 'accepted' : 'rejected', $adminSearchNote->getId()->toHex());

    $deleteTarget = RumourFactory::createTextNote(
        $adminPubkey,
        'This event will be deleted shortly.',
    )->sign($adminKeyPair, $signatureService);
    $result = $client->publishEvent($relayUrl, $deleteTarget)->await();
    printf("  Admin event 3 (deletion target): %s (id: %s)\n", $result->isAccepted() ? 'accepted' : 'rejected', $deleteTarget->getId()->toHex());

    printf("\n--- Step 2: Publish guest events via authenticated connection ---\n");

    $guestNote = RumourFactory::createTextNote(
        $guestKeyPair->getPublicKey(),
        'Hello from a guest user on the Nostr relay.',
        new TagCollection([
            Tag::hashtag('nostr'),
        ]),
    )->sign($guestKeyPair, $signatureService);
    $result = $client->publishEvent($relayUrl, $guestNote)->await();
    printf("  Guest event: %s (id: %s)\n", $result->isAccepted() ? 'accepted' : 'rejected', $guestNote->getId()->toHex());

    printf("\n--- Step 3: NIP-09 Delete admin event 3 ---\n");

    $deletion = RumourFactory::createEventDeletion(
        $adminPubkey,
        new TagCollection([
            Tag::event($deleteTarget->getId()),
            Tag::create(TagType::PARENT_KIND, (string) $deleteTarget->getKind()->toInt()),
        ]),
        'removing test event',
    )->sign($adminKeyPair, $signatureService);
    $result = $client->publishEvent($relayUrl, $deletion)->await();
    printf("  Deletion event: %s (id: %s)\n", $result->isAccepted() ? 'accepted' : 'rejected', $deletion->getId()->toHex());
    printf("  Targeted event: %s\n", $deleteTarget->getId()->toHex());

    $client->awaitPendingPublishes($relayUrl, 5.0);

    $client->disconnect($relayUrl);
    printf("  Disconnected authenticated client\n");

    printf("\n--- Step 4: Query as unauthenticated client ---\n");

    $unauthClient = NostrClientFactory::create(new NullLogger());
    $unauthClient->connect($relayUrl);
    printf("  Connected without auth\n");

    $allFilter = new Filter(
        kinds: EventKindCollection::fromInts([EventKind::TEXT_NOTE]),
        limit: 50,
    );

    $allHandler = new EventCollector();
    $subId = $unauthClient->subscribe($relayUrl, $allFilter, $allHandler);

    $timeout = 0;
    while (!$allHandler->hasReceivedEose() && $timeout < 50) {
        delay(0.1);
        ++$timeout;
    }

    $receivedEvents = $allHandler->getEvents();
    printf("  Received %d events (unauthenticated, should only see admin kind 1):\n", count($receivedEvents));
    foreach ($receivedEvents as $event) {
        $isAdmin = $event->getPubkey()->equals($adminPubkey) ? 'admin' : 'guest';
        printf("    [%s] kind:%d id:%s content:%s\n",
            $isAdmin,
            $event->getKind()->toInt(),
            substr($event->getId()->toHex(), 0, 16).'...',
            substr((string) $event->getContent(), 0, 60),
        );
    }

    $deletedStillExists = false;
    foreach ($receivedEvents as $event) {
        if ($event->getId()->equals($deleteTarget->getId())) {
            $deletedStillExists = true;
        }
    }
    printf("  Deleted event present: %s\n", $deletedStillExists ? 'yes (unexpected)' : 'no (correctly deleted)');

    $guestVisible = false;
    foreach ($receivedEvents as $event) {
        if ($event->getPubkey()->equals($guestKeyPair->getPublicKey())) {
            $guestVisible = true;
        }
    }
    printf("  Guest events visible: %s\n", $guestVisible ? 'yes (unexpected for unauth)' : 'no (correctly filtered)');

    printf("\n--- Step 5: NIP-50 Search ---\n");

    $searchFilter = new Filter(
        kinds: EventKindCollection::fromInts([EventKind::TEXT_NOTE]),
        search: 'decentralised',
        limit: 50,
    );

    $searchHandler = new EventCollector();
    $searchSubId = $unauthClient->subscribe($relayUrl, $searchFilter, $searchHandler);

    $timeout = 0;
    while (!$searchHandler->hasReceivedEose() && $timeout < 50) {
        delay(0.1);
        ++$timeout;
    }

    $searchEvents = $searchHandler->getEvents();
    printf("  Search for 'decentralised' returned %d event(s):\n", count($searchEvents));
    foreach ($searchEvents as $event) {
        printf("    id:%s content:%s\n",
            substr($event->getId()->toHex(), 0, 16).'...',
            substr((string) $event->getContent(), 0, 60),
        );
    }

    $unauthClient->unsubscribe($relayUrl, $subId);
    $unauthClient->unsubscribe($relayUrl, $searchSubId);
    $unauthClient->disconnect($relayUrl);
    printf("  Disconnected unauthenticated client\n");

    printf("\n=== Demo complete ===\n");
} catch (Throwable $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
