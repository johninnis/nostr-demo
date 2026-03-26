<?php

declare(strict_types=1);

namespace App\Infrastructure\Relay;

use Innis\Nostr\Core\Domain\Entity\Event;
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
}
