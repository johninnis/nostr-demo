<?php

declare(strict_types=1);

namespace App\Infrastructure\Relay;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Relay\Application\Port\RelayPolicyInterface;
use Innis\Nostr\Relay\Domain\Entity\RelayClient;

final class OpenPolicy implements RelayPolicyInterface
{
    public function allowEventSubmission(RelayClient $client, Event $event): void
    {
    }

    public function allowSubscription(RelayClient $client, array $filters): void
    {
    }

    public function filterForClient(RelayClient $client, array $filters): array
    {
        return $filters;
    }

    public function canClientReceiveEvent(RelayClient $client, Event $event): bool
    {
        return true;
    }

    public function getMaxSubscriptionsPerClient(): int
    {
        return 100;
    }
}
