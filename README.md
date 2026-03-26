# Nostr Demo

Demo project showcasing [nostr-core](https://github.com/johninnis/nostr-core), [nostr-client](https://github.com/johninnis/nostr-client), and [nostr-relay](https://github.com/johninnis/nostr-relay) working together.

Four standalone scripts demonstrate key generation, running a local relay, publishing events, and reading events back -- all with cryptographic signing and validation via nostr-core.

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

Starts a local in-memory relay server. Blocks until stopped with Ctrl+C.

```bash
php bin/start-relay.php [host] [port]
```

Defaults to `127.0.0.1:8080`.

### Publish Events

Generates a new identity and publishes three demo text notes to the relay.

```bash
php bin/publish-events.php [relay-url]
```

Defaults to `ws://127.0.0.1:8080`. Prints the public key hex for use with `read-events.php`.

### Read Events

Subscribes to text notes on the relay and displays them with full validation. Listens for 30 seconds then disconnects.

```bash
php bin/read-events.php [relay-url] [author-pubkey-hex]
```

Defaults to `ws://127.0.0.1:8080`. The author filter is optional -- omit it to receive all text notes.

## Full Walkthrough

Open three terminals:

```bash
# Terminal 1 - start the relay
php bin/start-relay.php

# Terminal 2 - publish events
php bin/publish-events.php
# note the pubkey hex from the output

# Terminal 3 - read events
php bin/read-events.php ws://127.0.0.1:8080 <pubkey-hex>
```

## Project Structure

```
bin/
  generate-keys.php        # Key generation and event signing demo
  start-relay.php          # Local relay server
  publish-events.php       # Publish demo events
  read-events.php          # Subscribe and read events
src/
  Infrastructure/
    Relay/
      InMemoryEventStore.php   # In-memory event storage
      OpenPolicy.php           # Permissive relay policy
      DemoRelayConfig.php      # Relay configuration
```

## Licence

MIT License. See LICENSE file for details.