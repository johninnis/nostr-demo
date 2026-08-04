<?php

declare(strict_types=1);

namespace App\Infrastructure\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Relay\Application\Port\RelayConfigInterface;
use Innis\Nostr\Relay\Domain\ValueObject\RateLimitConfig;
use Override;

final class DemoRelayConfig implements RelayConfigInterface
{
    /** @param int<1, 65535> $port */
    private function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly RelayUrl $relayUrl,
    ) {
    }

    public static function tryFrom(string $host = '127.0.0.1', int $port = 8080): ?self
    {
        if ($port < 1 || $port > 65535) {
            return null;
        }

        $relayUrl = RelayUrl::tryFromString('ws://'.$host.':'.$port);

        return null === $relayUrl ? null : new self($host, $port, $relayUrl);
    }

    /**
     * The listening address is the host's business from nostr-relay 0.6 on -- it is configured on the
     * HttpServer, not through RelayConfigInterface -- but the demo derives its relay URL from the same
     * pair, so it keeps them here and hands them to the server it owns.
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /** @return int<1, 65535> */
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
}
