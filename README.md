# Symfony Firestore Cache Adapter

[![Build Status](https://github.com/vasco-fund/symfony-firestore-cache-adapter/actions/workflows/ci.yml/badge.svg)](https://github.com/vasco-fund/symfony-firestore-cache-adapter/actions/workflows/ci.yml)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](phpstan.neon)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Latest Stable Version](https://poser.pugx.org/vasco-fund/symfony-firestore-cache-adapter/v/stable)](https://packagist.org/packages/vasco-fund/symfony-firestore-cache-adapter)

Production-ready Symfony Cache adapter for Google Cloud Firestore. Perfect for Cloud Run, App Engine, and other stateless GCP environments where filesystem cache is not persistent.

## Features

- **PSR-6 compliant** - Implementation of Psr\Cache\CacheItemPoolInterface
- **Namespace isolation** - Multi-tenant support with cache namespacing
- **High performance** - Batch operations and optimized Firestore queries
- **Cloud Run optimized** - Works seamlessly with ephemeral containers
- **Flexible client options** - Choose between HTTP client (no extensions required) or Google Cloud SDK (better performance)

## Installation

```bash
composer require vasco-fund/symfony-firestore-cache-adapter
```

### Optional: Google Cloud SDK

By default, this adapter uses the Firestore REST API via Symfony HTTP Client, which works out of the box without any PHP extensions.

For better performance, you can optionally install the Google Cloud SDK (requires the `grpc` PHP extension):

```bash
composer require google/cloud-firestore
```

⚠️ You will have to code you own client implementation to use the SDK.

## Requirements

- PHP 8.2 or higher
- Symfony 7.4 or 8.x
- Google Cloud Firestore credentials
- **Optional**: `grpc` PHP extension (only required if using Google Cloud SDK)

## Configuration

The adapter supports two Firestore client implementations:
- **HTTP Client** (default) - Uses Symfony HTTP Client, no extensions required

Planned improvements:
- **Google Cloud SDK** - Better performance, requires `grpc` extension

### 1. Basic Configuration (HTTP Client - Default)

```yaml
# config/packages/cache.yaml
framework:
    cache:
        # Unique name of your app: used to compute stable namespaces for cache keys.
        prefix_seed: your_app_name
        pools:
            app.cache:
                adapter: cache.adapter.firestore

# config/packages/services.yaml
services:
    # default configuration for services in *this* file
    _defaults:
        autowire: true # Automatically injects dependencies in your services.
        autoconfigure: true # Automatically registers your services as commands, event subscribers, etc.
    cache.adapter.firestore:
        class: Vasco\FirestoreCache\FirestoreAdapter
        public: true
        arguments:
          -   '%kernel.environment%'
          -   86400 # 24 hours
          -   null
          -   '@Vasco\FirestoreCache\Client\Http\FirestoreHttpClient'
          -   'symfony_cache'
    Vasco\FirestoreCache\Client\Http\FirestoreHttpClient:
        class: Vasco\FirestoreCache\Client\Http\FirestoreHttpClient
        arguments:
            $projectId: '%env(GOOGLE_CLOUD_PROJECT)%'
            $database: firestore_database
```

### Which Client Should I Use?

| Feature           | HTTP Client (Default)    | Google Cloud SDK                          |
|-------------------|--------------------------|-------------------------------------------|
| **Requirements**  | None                     | `grpc` PHP extension                      |
| **Installation**  | Works out of the box     | `composer require google/cloud-firestore` |
| **Performance**   | Good (~20-50ms latency)  | Better (~10-30ms latency)                 |
| **Ease of Setup** | Very easy                | May require extension compilation         |
| **Best For**      | Cloud Run, simple setups | High-performance needs                    |
| **Status**        | Implemented              | Planned                                   |

**Recommendation**: Start with HTTP client. Only switch to SDK if you need the extra performance and can install the grpc extension.

## Usage Examples

### Basic Cache Operations

```php
use Psr\Cache\CacheItemPoolInterface;

class YourService
{
    public function __construct(
        private CacheItemPoolInterface $cache
    ) {}

    public function getData(string $id): array
    {
        $item = $this->cache->getItem('data_' . $id);

        if (!$item->isHit()) {
            $data = $this->fetchDataFromDatabase($id);
            $item->set($data);
            $item->expiresAfter(3600); // 1 hour
            $this->cache->save($item);
        }

        return $item->get();
    }
}
```

## Environment Variables

### For HTTP Client

```bash
# Required
GOOGLE_CLOUD_PROJECT=your-project-id
```

## Performance Considerations

### Storage Costs

- Firestore charges for storage and per document read/write operation
- Use appropriate TTL values to minimize storage
- Consider namespacing for cache isolation

### Latency

- Firestore adds network latency (~10-50ms), it should not be used as the main cache backend for application requiring high performance
- Use batch operations when possible (implemented internally)
- Consider longer TTL for frequently accessed data

### Best Practices

1. **Set appropriate TTL** - Don't cache forever
2. **Monitor Firestore usage** in GCP Console
3. **Use namespaces** for multi-environment setups

## Testing

### Run Tests

```bash
composer test
```

### With Coverage

```bash
composer test:coverage
```

### With mutation Testing

```bash
composer infection
```

### Static Analysis

```bash
composer phpstan
```

### Code Style

```bash
composer cs:check
composer cs:fix
```

### Full Quality Assurance

```bash
composer qa
```

## Firestore Structure

### Cache Items Collection

```
symfony_cache (collection)
├── {namespace}:{key} (document)
│   ├── value: string (compressed or raw)
│   ├── expiresAt: timestamp
│   ├── version: int
│   └── compressed: bool
```

## Security

If you discover any security-related issues, please email remi@vasco.fund instead of using the issue tracker.

## Credits

- [Rémi Janot](https://github.com/rjanot)
- [All Contributors](https://github.com/vasco-fund/symfony-firestore-cache-adapter/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Roadmap

- [ ] Support for Firestore using gRPC

## Alternatives

- **Memcached/Redis on GCP** - Better performance but requires always-up service
- **Symfony's ChainAdapter** - Combine Firestore with APCu for local cache

## FAQ

**Q: Why Firestore instead of Redis/Memcached?**
A: Firestore is serverless, scales automatically, and works perfectly with Cloud Run's ephemeral containers. No need to manage a separate cache cluster.

**Q: What about costs?**
A: Firestore pricing is per storage size and per operation. With compression and proper TTL, costs are typically $5-20/month for small to medium applications.

**Q: Can I use this in production?**
A: Yes! This adapter is production-ready with comprehensive tests, strict type checking (PHPStan level 9), and follows Symfony best practices.

**Q: Does it work with Symfony 6?**
A: Yes, it supports Symfony 7.4 LTS and Symfony 8.x.

**Q: Do I need the grpc extension?**
A: No! By default, the adapter uses Symfony HTTP Client which requires no extensions. You can optionally use the Google Cloud SDK for better performance if you have the grpc extension installed.

**Q: Which client should I use?**
A: Start with the HTTP client (default) - it's easier to set up and works everywhere. Switch to the SDK client only if you need the extra performance and can install the grpc extension.
