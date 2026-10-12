# Nostr Demo

[![CI](https://github.com/johninnis/nostr-demo/actions/workflows/ci.yml/badge.svg)](https://github.com/johninnis/nostr-demo/actions/workflows/ci.yml)

Demo project showcasing [nostr-core](https://github.com/johninnis/nostr-core), [nostr-client](https://github.com/johninnis/nostr-client), and [nostr-relay](https://github.com/johninnis/nostr-relay) working together.

Four standalone scripts demonstrate key generation, running a local relay with tenant-based access control, publishing events with NIP-42 authentication, and reading events as a guest.

## Requirements

- PHP 8.4 or higher
- The `ctype`, `filter`, `gmp`, `intl`, `mbstring`, `openssl` and `sodium` extensions, which nostr-core and the amphp packages require
- The `pcntl` extension, which `bin/start-relay.php` uses to stop on Ctrl+C (`SIGINT`) or `SIGTERM`
- The `ffi` extension, which `composer.json` declares among its development requirements, so `composer install` needs it unless run with `--no-dev`. With the native `libsecp256k1` library installed, nostr-core signs and verifies through it; without it, nostr-core falls back to a slower pure-PHP path that is not constant-time

## Dependencies

| Package | Version |
| --- | --- |
| [innis/nostr-core](https://github.com/johninnis/nostr-core) | `^0.10` |
| [innis/nostr-client](https://github.com/johninnis/nostr-client) | `^0.10` |
| [innis/nostr-relay](https://github.com/johninnis/nostr-relay) | `^0.13` |
| [amphp/http-server](https://github.com/amphp/http-server) | `^3.0` |

nostr-relay leaves the HTTP server to its host, so `bin/start-relay.php` constructs the `SocketHttpServer`, exposes it on the relay's listening address, and mounts the relay's request handler on it. The in-memory event store ships with the library, so the demo carries none of its own.

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
php bin/read-events.php [relay-url] [author-pubkey-hex] [search-term]
```

Defaults to `ws://127.0.0.1:8080`. The author filter and search term are optional -- omit them to receive all text notes.

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
      DemoRelayConfig.php
```

The relay policy and the event store both come from the library (`RelayPolicy` and `InMemoryEventStore`), so the demo keeps only its own relay configuration and the client-side event collector.

## Licence

MIT License. See LICENSE file for details.
