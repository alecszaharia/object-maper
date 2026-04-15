# CLAUDE.md

Guidance for Claude Code (claude.ai/code) working with this repo.

## Project

**alecszaharia/simmap** — Symmetrical (bidirectional) object mapper for PHP 8.2+ using attributes. Maps objects between class pairs both declaring `#[Mappable]` pointing at each other.

Namespace: `Alecszaharia\Simmap`

## Commands

All commands via Docker (`mapper:latest` image). Build image first with `make build`.

```bash
make test                                          # Run all tests
make test-file FILE=tests/Unit/MapperTest.php      # Run single test file
make test-filter FILTER=testMethodName             # Run tests matching filter
make benchmark                                     # Run performance benchmarks
make build                                         # Build Docker image
```

Direct PHP (no Docker): `php vendor/bin/phpunit`

## Architecture

### Core flow: Attributes → Metadata → Mapping

1. **Attributes** (`src/Attribute/`) — PHP 8 attributes configure mapping at class/property level:
   - `#[Mappable(targetClass: Foo::class)]` on class — declares mapping relationship. Both classes must point to each other (reciprocal requirement)
   - `#[MapTo(targetProperty: 'name', targetClass: Item::class)]` on property — custom property name mapping, dot notation for nested paths, `targetClass` for array item types
   - `#[IgnoreMap]` on property — exclude from mapping

2. **MetadataReader** (`src/Metadata/MetadataReader.php`) — Reflects on both classes, builds bidirectional `PropertyMapping` objects. Collects mappings from both sides, deduplicates. Detects array properties via type hints.

3. **MappingMetadata** (`src/Metadata/MappingMetadata.php`) — Immutable value object holding property mappings for class pair. Direction-independent: `getMappingsForDirection()` reverses mappings when needed.

4. **Mapper** (`src/Mapper.php`) — Entry point. LRU-cached metadata lookup, uses Symfony PropertyAccessor for read/write. Handles nested path auto-initialization, array/collection mapping with recursive `map()` calls, circular reference detection via stack tracking.

### Key design decisions

- **Bidirectional by default**: Metadata cached per unordered class pair (A+B = B+A). `PropertyMapping::reverse()` swaps direction.
- **Null skipping**: Source properties with `null` silently skipped (partial updates).
- **PropertyAccessException silently caught**: In `executeMapping()`, property access failures caught and skipped (not thrown).
- **Instantiation via `newInstanceWithoutConstructor()`**: Target objects bypass constructors.

## Tests

Tests in `tests/Unit/`. Fixtures in `tests/Fixtures/` — paired classes (e.g., `User`/`UserDTO`, `Company`/`CompanyDTO`) with attributes pre-configured for test scenarios.