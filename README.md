# Nostr Demo

Demo project showcasing [nostr-core](https://github.com/johninnis/nostr-core), [nostr-client](https://github.com/johninnis/nostr-client), and [nostr-relay](https://github.com/johninnis/nostr-relay) working together.

Four standalone scripts demonstrate key generation, running a local relay with tenant-based access control, publishing events with NIP-42 authentication, and reading events as a guest.

## Requirements

- PHP 8.3 or higher

## Install

```bash
composer install
```

## Scripts

### Generate Keys

Standalone demonstration of key generation, event creation, signing, and validation. No relay needed.

```bash
php bin/generate-keys.php
```

Outputs a new key pair (hex and bech32), creates a text note, signs it, verifies the signature, runs full validation, and prints the event JSON.

### Start Relay

Starts a local in-memory relay server with tenant-based access control via `RelayPolicy`. Guests can read kind 0 and 1 events from tenants. Blocks until stopped with Ctrl+C.

```bash
php bin/start-relay.php 127.0.0.1 8080 <admin-pubkey-hex>
```

The admin pubkey hex is required as the third argument and is configured as the relay tenant.

### Publish Events

Connects to the relay with the admin private key, authenticates via NIP-42, and demonstrates event publishing, NIP-09 deletion, NIP-50 search, and guest vs admin event visibility.

```bash
php bin/publish-events.php ws://127.0.0.1:8080 <admin-private-key-hex>
```

### Read Events

Subscribes to text notes on the relay and displays them with full validation. Connects as an unauthenticated client (guest), so only sees tenant events matching guest read rules. Listens for 30 seconds then disconnects.

```bash
php bin/read-events.php [relay-url] [author-pubkey-hex]
```

Defaults to `ws://127.0.0.1:8080`. The author filter is optional -- omit it to receive all text notes.

## Full Walkthrough

```bash
# Terminal 1 - generate keys and start relay
php bin/generate-keys.php
# copy the hex private key and public key

php bin/start-relay.php 127.0.0.1 8080 <public-key-hex>

# Terminal 2 - publish and query
php bin/publish-events.php ws://127.0.0.1:8080 <private-key-hex>

# Optional: read as unauthenticated guest
php bin/read-events.php ws://127.0.0.1:8080
```

## Project Structure

```
bin/
  generate-keys.php
  start-relay.php
  publish-events.php
  read-events.php
src/
  Infrastructure/
    Client/
      EventCollector.php
    Relay/
      InMemoryEventStore.php
      DemoRelayConfig.php
```

The relay policy now comes from the library (`RelayPolicy`), so no local policy classes are needed.

## Licence

MIT License. See LICENSE file for details.
