<?php

declare(strict_types=1);

namespace App\Infrastructure\Client;

use Innis\Nostr\Core\Application\Port\EventHandlerInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;

final class EventCollector implements EventHandlerInterface
{
    private array $events = [];
    private bool $eoseReceived = false;

    public function handleEvent(Event $event, SubscriptionId $subscriptionId): void
    {
        $this->events[] = $event;
    }

    public function handleEose(SubscriptionId $subscriptionId): void
    {
        $this->eoseReceived = true;
    }

    public function handleClosed(SubscriptionId $subscriptionId, string $message): void
    {
    }

    public function handleNotice(RelayUrl $relayUrl, string $message): void
    {
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function hasReceivedEose(): bool
    {
        return $this->eoseReceived;
    }
}
