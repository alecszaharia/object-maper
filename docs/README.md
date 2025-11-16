# Simmap Documentation

Welcome to the Simmap documentation! This directory contains comprehensive guides and references for using Simmap effectively.

## Getting Started

New to Simmap? Start with the main [README](../README.md) for installation and basic usage.

## Documentation Index

### Core Documentation

- **[README](../README.md)** - Installation, basic usage, and quick start
- **[LICENSE](../LICENSE)** - MIT license terms
- **[CONTRIBUTING](../CONTRIBUTING.md)** - How to contribute to Simmap
- **[SECURITY](../SECURITY.md)** - Security policy and reporting vulnerabilities

### Advanced Guides

- **[Architecture](architecture.md)** - Internal architecture and design decisions
  - Component overview
  - Symmetrical mapping implementation
  - Performance optimizations
  - Extension points

- **[Examples](examples.md)** - Real-world use cases and patterns
  - E-commerce scenarios
  - API request/response mapping
  - Form handling
  - Multi-level nesting
  - Collection mapping
  - Integration patterns (CQRS, Repository, Service Layer)

- **[Performance](performance.md)** - Optimization and benchmarking
  - Performance characteristics
  - Benchmarking techniques
  - Optimization strategies
  - Memory management
  - Production monitoring

- **[Troubleshooting](troubleshooting.md)** - Common issues and solutions
  - Property not being mapped
  - Nested properties
  - Exception handling
  - Performance issues
  - Symfony-specific problems
  - Testing issues

## Quick Reference

### Basic Mapping

**Important**: Classes must be marked with `#[Mappable]` attribute.

```php
use Alecszaharia\Simmap\Mapper;
use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable]
class DTO {
    public string $name;
}

#[Mappable]
class Entity {
    public string $name;
}

$mapper = new Mapper();
$entity = $mapper->map($dto, Entity::class);
```

### Custom Property Mapping

```php
use Alecszaharia\Simmap\Attribute\MapTo;
use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable]
class DTO {
    #[MapTo('targetProperty')]
    public string $sourceProperty;
}
```

### Nested Property Mapping

```php
use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable]
class DTO {
    #[MapTo('user.address.city')]
    public string $city;
}
```

### Ignoring Properties

```php
use Alecszaharia\Simmap\Attribute\Ignore;
use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable]
class DTO {
    #[Ignore]
    public string $internalData;
}
```

### Array Mapping

```php
use Alecszaharia\Simmap\Attribute\MapArray;
use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable]
class OrderDTO {
    #[MapArray(OrderItem::class)]
    public array $items = [];
}

#[Mappable]
class Order {
    #[MapArray(OrderItemDTO::class)]
    public array $items = [];
}

// Automatically maps each array element bidirectionally
$order = $mapper->map($orderDto, Order::class);
```

## Common Use Cases

### 1. API Request to Entity
See [Examples - API Request/Response Mapping](examples.md#api-requestresponse-mapping)

### 2. Entity to Response DTO
See [Examples - E-commerce Product Management](examples.md#e-commerce-product-management)

### 3. Form Data to Entity
See [Examples - Form Handling](examples.md#form-handling)

### 4. Partial Updates
See [Examples - Partial Updates](examples.md#partial-updates)

### 5. Symfony Integration
See [README - Symfony Integration](../README.md#symfony-integration)

## FAQ

### How does symmetrical mapping work?

Simmap automatically handles bidirectional mapping. If you define `#[MapTo('fullName')]` on a `name` property in a DTO, mapping from the entity back to the DTO will automatically map `fullName` → `name`.

See [Architecture - Symmetrical Mapping](architecture.md#symmetrical-mapping) for details.

### What's the performance impact?

- First mapping: ~10ms (metadata reading)
- Subsequent mappings: ~0.5ms (cached)
- Negligible in most web applications

See [Performance Guide](performance.md) for benchmarks and optimization tips.

### How do I handle collections/arrays?

Use the `#[MapArray]` attribute for automatic array element mapping:

```php
use Alecszaharia\Simmap\Attribute\MapArray;

class OrderDTO {
    #[MapArray(OrderItem::class)]
    public array $items = [];
}

$order = $mapper->map($orderDto, Order::class);
// All items are automatically mapped!
```

The `#[MapArray]` attribute:
- Works bidirectionally (symmetrical mapping)
- Preserves array keys (indexed and associative)
- Handles mixed content (objects are mapped, scalars preserved)

See [Examples - Array Mapping](examples.md#array-mapping-with-maparray) for detailed examples.

For standalone arrays (not properties), use a helper:
```php
$entities = array_map(
    fn($dto) => $mapper->map($dto, Entity::class),
    $dtos
);
```

### Can I map to existing instances?

Yes! Just pass the instance instead of a class name:

```php
$existingEntity = $repository->find($id);
$mapper->map($dto, $existingEntity);
```

### What happens to null values?

Null values are mapped normally. They will overwrite existing values in the target.

See [Troubleshooting - Null Values](troubleshooting.md#null-values-being-transferred) for filtering strategies.

### How do I handle validation?

Validate before mapping:

```php
$violations = $validator->validate($dto);
if (count($violations) > 0) {
    throw new ValidationException($violations);
}

$entity = $mapper->map($dto, Entity::class);
```

### Is it thread-safe?

The metadata cache uses static storage, which is not thread-safe for writes. However:
- Single-threaded PHP (typical): No issues
- Multi-threaded (Swoole, etc.): Warm up cache at boot to avoid concurrent writes

See [Architecture - Thread Safety](architecture.md#thread-safety).

## Comparison with Alternatives

| Feature | Simmap | AutoMapper PHP | Serializer |
|---------|--------|----------------|------------|
| Attribute-based | ✅ | ❌ | ✅ |
| Symmetrical mapping | ✅ | ❌ | ❌ |
| Nested paths | ✅ | ✅ | ❌ |
| Performance | Fast | Medium | Slow |
| Symfony integration | ✅ | ✅ | ✅ |
| Learning curve | Low | Medium | High |

## Support

- **Issues**: [GitHub Issues](https://github.com/alecszaharia/simmap/issues)
- **Discussions**: [GitHub Discussions](https://github.com/alecszaharia/simmap/discussions)
- **Security**: See [SECURITY.md](../SECURITY.md)

## Contributing

We welcome contributions! See [CONTRIBUTING.md](../CONTRIBUTING.md) for:
- Development setup
- Coding standards
- Testing requirements
- Pull request process

## Version History

See the [Releases page](https://github.com/alecszaharia/simmap/releases) for version history and release notes.

## License

Simmap is open-source software licensed under the [MIT license](../LICENSE).

---

**Need help?** Check the [Troubleshooting Guide](troubleshooting.md) or [open an issue](https://github.com/alecszaharia/simmap/issues).

**Want to contribute?** Read the [Contributing Guide](../CONTRIBUTING.md) to get started.

**Looking for examples?** See the [Examples Guide](examples.md) for real-world use cases.
