<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\Service\EventValidator;
use Innis\Nostr\Core\Domain\Service\NipComplianceValidator;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;

try {
    printf("=== Nostr Key Generation Demo ===\n\n");

    $signatureService = Secp256k1Signer::create();

    $keyPair = KeyPair::generate($signatureService);
    $privateKey = $keyPair->getPrivateKey();
    $publicKey = $keyPair->getPublicKey();

    printf("Private Key (hex):  %s\n", $privateKey->toHex());
    printf("Private Key (nsec): %s\n", $privateKey->toBech32());
    printf("Public Key (hex):   %s\n", $publicKey->toHex());
    printf("Public Key (npub):  %s\n\n", $publicKey->toBech32());

    printf("=== Event Creation and Signing ===\n\n");

    $rumour = RumourFactory::createTextNote($publicKey, 'Hello from nostr-demo! This is a signed text note.');

    printf("Unsigned rumour created (kind %d)\n", $rumour->getKind()->toInt());

    $signedEvent = $rumour->sign($keyPair, $signatureService);

    printf("Rumour signed into an event\n");
    printf("Event ID: %s\n\n", $signedEvent->getId()->toHex());

    printf("=== Signature Verification ===\n\n");

    $isValid = $signedEvent->verify($signatureService);
    printf("Signature valid: %s\n\n", $isValid ? 'yes' : 'no');

    printf("=== Full Event Validation ===\n\n");

    $validationService = new EventValidator($signatureService, new NipComplianceValidator($signatureService));
    $validationService->validateEvent($signedEvent);
    printf("Event passed full validation (timestamp, content, tags, signature)\n\n");

    printf("=== Event JSON ===\n\n");

    printf("%s\n", json_encode($signedEvent->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} catch (Throwable $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
