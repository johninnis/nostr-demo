<?php

declare(strict_types=1);

namespace App\Infrastructure\Client;

use Innis\Nostr\Core\Application\Port\EventHandlerInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final class EventCollector implements EventHandlerInterface
{
    /** @var list<Event> */
    private array $events = [];
    private bool $eoseReceived = false;

    #[Override]
    public function handleEvent(Event $event, SubscriptionId $subscriptionId): void
    {
        $this->events[] = $event;
    }

    #[Override]
    public function handleEose(SubscriptionId $subscriptionId): void
    {
        $this->eoseReceived = true;
    }

    #[Override]
    public function handleClosed(SubscriptionId $subscriptionId, string $message): void
    {
    }

    #[Override]
    public function handleNotice(RelayUrl $relayUrl, string $message): void
    {
    }

    /** @return list<Event> */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function hasReceivedEose(): bool
    {
        return $this->eoseReceived;
    }
}
