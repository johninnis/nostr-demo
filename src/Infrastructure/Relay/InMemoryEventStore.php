<?php

declare(strict_types=1);

namespace App\Infrastructure\Relay;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Relay\Application\Port\RelayEventStoreInterface;

final class InMemoryEventStore implements RelayEventStoreInterface
{
    /** @var array<string, Event> */
    private array $events = [];

    public function store(Event $event): bool
    {
        $id = $event->getId()->toHex();

        if (isset($this->events[$id])) {
            return false;
        }

        $this->events[$id] = $event;

        return true;
    }

    public function findByFilters(array $filters, int $limit = 100): array
    {
        $matched = [];

        foreach ($this->events as $event) {
            foreach ($filters as $filter) {
                if ($filter->matches($event)) {
                    $matched[] = $event;
                    break;
                }
            }

            if (count($matched) >= $limit) {
                break;
            }
        }

        return $matched;
    }

    public function countByFilters(array $filters): int
    {
        return count($this->findByFilters($filters, PHP_INT_MAX));
    }

    public function deleteByEventIds(array $eventIds, PublicKey $author): int
    {
        $deleted = 0;

        foreach ($eventIds as $eventId) {
            $hex = $eventId->toHex();

            if (isset($this->events[$hex]) && $this->events[$hex]->getPubkey()->equals($author)) {
                unset($this->events[$hex]);
                ++$deleted;
            }
        }

        return $deleted;
    }

    public function deleteByCoordinates(array $coordinates, PublicKey $author): int
    {
        $deleted = 0;

        foreach ($this->events as $id => $event) {
            if (!$event->getPubkey()->equals($author)) {
                continue;
            }

            foreach ($coordinates as $coordinate) {
                if ($coordinate->matchesEvent($event)) {
                    unset($this->events[$id]);
                    ++$deleted;
                    break;
                }
            }
        }

        return $deleted;
    }
}
