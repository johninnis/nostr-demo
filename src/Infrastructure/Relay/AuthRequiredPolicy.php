<?php

declare(strict_types=1);

namespace App\Infrastructure\Relay;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Relay\Application\Port\RelayPolicyInterface;
use Innis\Nostr\Relay\Application\Service\AuthenticationManager;
use Innis\Nostr\Relay\Domain\Entity\RelayClient;
use Innis\Nostr\Relay\Domain\Exception\AuthRequiredException;

final class AuthRequiredPolicy implements RelayPolicyInterface
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly string $adminPubkeyHex,
    ) {
    }

    public function allowEventSubmission(RelayClient $client, Event $event): void
    {
        if (!$this->authManager->isAuthenticated($client->getId())) {
            throw new AuthRequiredException('authentication required to submit events');
        }
    }

    public function allowSubscription(RelayClient $client, array $filters): void
    {
    }

    public function filterForClient(RelayClient $client, array $filters): array
    {
        if ($this->authManager->isAuthenticated($client->getId())) {
            return $filters;
        }

        return array_map(
            fn (Filter $filter) => new Filter(
                ids: $filter->getIds(),
                authors: [$this->adminPubkeyHex],
                kinds: [EventKind::TEXT_NOTE],
                tags: $filter->getTags(),
                since: $filter->getSince(),
                until: $filter->getUntil(),
                limit: $filter->getLimit(),
                search: $filter->getSearch(),
            ),
            $filters
        );
    }

    public function canClientReceiveEvent(RelayClient $client, Event $event): bool
    {
        if ($this->authManager->isAuthenticated($client->getId())) {
            return true;
        }

        return $event->getPubkey()->toHex() === $this->adminPubkeyHex
            && EventKind::TEXT_NOTE === $event->getKind()->toInt();
    }

    public function getMaxSubscriptionsPerClient(): int
    {
        return 100;
    }
}
