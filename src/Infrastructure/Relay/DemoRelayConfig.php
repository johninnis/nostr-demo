<?php

declare(strict_types=1);

namespace App\Infrastructure\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Relay\Application\Port\RelayConfigInterface;
use Innis\Nostr\Relay\Domain\ValueObject\RateLimitConfig;
use InvalidArgumentException;
use Override;

final class DemoRelayConfig implements RelayConfigInterface
{
    private readonly RelayUrl $relayUrl;

    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8080,
    ) {
        $this->relayUrl = RelayUrl::tryFromString('ws://'.$host.':'.$port)
            ?? throw new InvalidArgumentException(sprintf('Invalid relay URL: ws://%s:%d', $host, $port));
    }

    #[Override]
    public function getHost(): string
    {
        return $this->host;
    }

    #[Override]
    public function getPort(): int
    {
        return $this->port;
    }

    #[Override]
    public function getMaxConnections(): int
    {
        return 100;
    }

    public function getRelayInfo(): Nip11Info
    {
        return Nip11Info::fromArray($this->relayUrl, [
            'name' => 'Nostr Demo Relay',
            'description' => 'A local demo relay for testing',
            'supported_nips' => [1, 9, 11, 42, 50],
            'software' => 'innis/nostr-relay',
            'version' => 'dev',
        ]);
    }

    #[Override]
    public function getRelayUrl(): RelayUrl
    {
        return $this->relayUrl;
    }

    public function getRateLimitConfig(): RateLimitConfig
    {
        return new RateLimitConfig(
            eventsPerMinute: 600,
            subscriptionsPerMinute: 600,
        );
    }

    #[Override]
    public function getTrustedProxies(): array
    {
        return [];
    }
}
