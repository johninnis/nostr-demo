<?php

declare(strict_types=1);

namespace App\Infrastructure\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Relay\Application\Port\RelayConfigInterface;
use Innis\Nostr\Relay\Domain\ValueObject\RateLimitConfig;
use InvalidArgumentException;

final class DemoRelayConfig implements RelayConfigInterface
{
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8080,
    ) {
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getMaxConnections(): int
    {
        return 100;
    }

    public function getRelayInfo(): Nip11Info
    {
        $relayUrl = RelayUrl::fromString('ws://'.$this->host.':'.$this->port)
            ?? throw new InvalidArgumentException('Invalid relay URL: ws://'.$this->host.':'.$this->port);

        return new Nip11Info(
            relayUrl: $relayUrl,
            name: 'Nostr Demo Relay',
            description: 'A local demo relay for testing',
            supportedNips: [1, 9, 11, 42, 50],
            software: 'innis/nostr-relay',
            version: 'dev',
        );
    }

    public function getRelayUrl(): RelayUrl
    {
        return RelayUrl::fromString('ws://'.$this->host.':'.$this->port)
            ?? throw new InvalidArgumentException('Invalid relay URL: ws://'.$this->host.':'.$this->port);
    }

    public function getRateLimitConfig(): RateLimitConfig
    {
        return new RateLimitConfig(
            eventsPerMinute: 600,
            subscriptionsPerMinute: 600,
        );
    }

    public function getTrustedProxies(): array
    {
        return [];
    }
}
