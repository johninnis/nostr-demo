<?php

declare(strict_types=1);

namespace App\Infrastructure\Relay;

use Amp\Socket\InternetAddress;
use Innis\Nostr\Core\Domain\ValueObject\EventLimits;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Relay\Application\Port\RelayConfigInterface;
use Innis\Nostr\Relay\Domain\ValueObject\RateLimitConfig;
use Override;

final readonly class DemoRelayConfig implements RelayConfigInterface
{
    private function __construct(
        private InternetAddress $listeningAddress,
        private RelayUrl $relayUrl,
    ) {
    }

    public static function tryFrom(string $host = '127.0.0.1', int $port = 8080): ?self
    {
        if ($port < 1) {
            return null;
        }

        $listeningAddress = InternetAddress::tryFromString($host.':'.$port);
        if (null === $listeningAddress) {
            return null;
        }

        $relayUrl = RelayUrl::tryFromString('ws://'.$listeningAddress->toString());

        return null === $relayUrl ? null : new self($listeningAddress, $relayUrl);
    }

    public function getListeningAddress(): InternetAddress
    {
        return $this->listeningAddress;
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
            'supported_nips' => [1, 9, 11, 17, 40, 42, 45, 50, 59, 67, 70],
            'software' => 'https://github.com/johninnis/nostr-relay',
            'version' => 'dev',
        ]);
    }

    #[Override]
    public function getRelayUrl(): RelayUrl
    {
        return $this->relayUrl;
    }

    #[Override]
    public function getEventLimits(): EventLimits
    {
        return new EventLimits();
    }

    public function getRateLimitConfig(): RateLimitConfig
    {
        return new RateLimitConfig(
            eventsPerMinute: 600,
            subscriptionsPerMinute: 600,
        );
    }
}
