<?php

declare(strict_types=1);

namespace App\Infrastructure\Relay;

use Innis\Nostr\Core\Domain\Collection\EventCollection;
use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Relay\Application\Port\RelayEventStoreInterface;
use Innis\Nostr\Relay\Domain\Enum\EventStoreOutcome;

final class InMemoryEventStore implements RelayEventStoreInterface
{
    /** @var array<string, Event> */
    private array $events = [];

    public function store(Event $event): EventStoreOutcome
    {
        $id = $event->getId()->toHex();

        if (isset($this->events[$id])) {
            return EventStoreOutcome::Duplicate;
        }

        $this->events[$id] = $event;

        return EventStoreOutcome::Stored;
    }

    public function findByFilters(FilterCollection $filters, int $limit = 100): EventCollection
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

        return new EventCollection($matched);
    }

    public function countByFilters(FilterCollection $filters): int
    {
        return $this->findByFilters($filters, PHP_INT_MAX)->count();
    }

    public function deleteByEventIds(EventIdCollection $eventIds, PublicKey $author): int
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

    public function deleteByCoordinates(EventCoordinateCollection $coordinates, PublicKey $author): int
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
