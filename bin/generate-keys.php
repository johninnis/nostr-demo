<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\Service\EventValidationService;
use Innis\Nostr\Core\Domain\Service\NipComplianceValidator;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Infrastructure\Adapter\Secp256k1SignatureAdapter;

printf("=== Nostr Key Generation Demo ===\n\n");

$signatureService = Secp256k1SignatureAdapter::create();

$keyPair = KeyPair::generate($signatureService);
$privateKey = $keyPair->getPrivateKey();
$publicKey = $keyPair->getPublicKey();

printf("Private Key (hex):  %s\n", $privateKey->toHex());
printf("Private Key (nsec): %s\n", $privateKey->toBech32());
printf("Public Key (hex):   %s\n", $publicKey->toHex());
printf("Public Key (npub):  %s\n\n", $publicKey->toBech32());

printf("=== Event Creation and Signing ===\n\n");

$unsignedEvent = EventFactory::createTextNote($publicKey, 'Hello from nostr-demo! This is a signed text note.');

printf("Unsigned event created (kind %d)\n", $unsignedEvent->getKind()->toInt());
printf("Event is signed: %s\n\n", $unsignedEvent->isSigned() ? 'yes' : 'no');

$signedEvent = $unsignedEvent->sign($keyPair, $signatureService);

printf("Event signed successfully\n");
printf("Event ID: %s\n", $signedEvent->getId()->toHex());
printf("Event is signed: %s\n\n", $signedEvent->isSigned() ? 'yes' : 'no');

printf("=== Signature Verification ===\n\n");

$isValid = $signedEvent->verify($signatureService);
printf("Signature valid: %s\n\n", $isValid ? 'yes' : 'no');

printf("=== Full Event Validation ===\n\n");

$validationService = new EventValidationService($signatureService, new NipComplianceValidator($signatureService));
$validationService->validateEvent($signedEvent);
printf("Event passed full validation (timestamp, content, tags, signature)\n\n");

printf("=== Event JSON ===\n\n");

printf("%s\n", json_encode($signedEvent->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
