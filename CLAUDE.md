# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Running Commands

All PHP scripts must be executed using Docker to ensure consistent environment. A Makefile is provided for convenience.

### Available Make Targets

**Show all available commands:**
```bash
make help
```

### Testing

**Run all tests:**
```bash
make test
```

**Run specific test file:**
```bash
make test-file FILE=tests/Unit/MapperTest.php
```

**Run single test method:**
```bash
make test-filter FILTER=testMethodName
```

**Run with coverage report:**
```bash
make test-coverage
```

### Running Examples

```bash
make example
```

### Direct Docker Commands (if needed)

If you need to run PHP commands directly without Make:

```bash
docker run --rm -it -v $(pwd):/app --user $(id -u):$(id -g) -w /app tools:latest php {argument}
```

Or use the Makefile helper:
```bash
make php CMD="path/to/script.php"
```

## Architecture Overview

Simmap is a **symmetrical object mapper** library for PHP 8.1+. The key architectural concept is **bidirectional mapping** - a single mapping definition works in both directions (A→B and B→A).

**IMPORTANT**: All classes participating in mapping (both source and target) **must** be marked with the `#[Mappable]` attribute. This is a required type safety mechanism that provides explicit opt-in control. Attempting to map classes without this attribute will throw `MappingException::notMappable()`.

### Core Components

**1. Mapper (`src/Mapper.php`)**
- Entry point for all mapping operations
- Orchestrates property transfer between objects
- Implements three-tier property resolution strategy:
  1. Check for explicit `#[MapTo]` attribute on source property
  2. Check for reverse mapping in target metadata (symmetrical mapping)
  3. Auto-map if property names match and target property exists
- Uses `newInstanceWithoutConstructor()` to create target objects (avoids dependency injection issues)
- **Error handling strategy**: Graceful on reads (skip uninitialized/inaccessible properties), strict on writes (throw exceptions)

**2. MetadataReader (`src/Metadata/MetadataReader.php`)**
- Scans classes using PHP Reflection API to extract attribute metadata
- Maintains in-memory cache: `array<string, MappingMetadata>`
- Cache is per class name (not per instance) for memory efficiency
- **Validates `#[Mappable]` attribute presence** - skips property processing for non-mappable classes
- Processes both public and protected properties when class is mappable
- Reads `#[MapTo]`, `#[MapArray]`, `#[Ignore]`, and `#[Mappable]` attributes

**3. MappingMetadata (`src/Metadata/MappingMetadata.php`)**
- Data container for all mapping information about a class
- Stores cached `ReflectionClass` instance to avoid re-creation
- Contains dual indexes for O(1) lookups in both directions:
  - `forwardMappingIndex`: source → target (O(1))
  - `reverseMappingIndex`: target → source (O(1))
- Validates and prevents duplicate property mappings
- Uses associative array for O(1) ignored property checks

**4. PropertyAccessor (Symfony Component)**
- External dependency handling property reading/writing
- Supports: public properties, getters/setters, magic methods, nested paths
- Enables nested property mapping like `address.city.name`

### Symmetrical Mapping Implementation

The core innovation is the **dual index system** for O(1) lookups in both directions:

```php
// In DTO class
class UserDTO {
    #[MapTo('fullName')]
    public string $name;
}

// MetadataReader builds both indexes:
$metadata->forwardMappingIndex = ['name' => 'fullName'];  // O(1) forward lookup
$metadata->reverseMappingIndex = ['fullName' => 'name'];  // O(1) reverse lookup

// Forward mapping (DTO → Entity): Uses forward index (O(1))
// Reverse mapping (Entity → DTO): Uses reverse index (O(1))
```

This allows bidirectional mapping with a single attribute definition, with constant-time performance in both directions.

### Property Resolution Algorithm

For each source property, the mapper determines the target path:

1. **Explicit mapping**: Check if source has `#[MapTo('target')]` attribute
2. **Reverse mapping**: Check if target has a mapping pointing back to this source property
3. **Auto-mapping**: If property exists on target with same name and not ignored
4. **Skip**: If none of the above match

This three-tier approach enables both convention (auto-mapping) and configuration (attributes).

## Code Organization

```
src/
├── Mapper.php              # Main mapper implementation
├── MapperInterface.php     # Mapper contract
├── Attribute/
│   ├── MapTo.php          # Attribute for custom property mapping
│   ├── MapArray.php       # Attribute for array element mapping
│   ├── Ignore.php         # Attribute to exclude properties
│   └── Mappable.php       # Attribute to mark classes as mappable
├── Exception/
│   └── MappingException.php  # Custom exceptions with factory methods
└── Metadata/
    ├── MetadataReader.php     # Reflection-based attribute reader
    ├── MappingMetadata.php    # Metadata container with reverse index
    ├── PropertyMapping.php    # Single property mapping representation
    └── ArrayMapping.php       # Array property mapping representation
```

## Development Guidelines

### PHP Standards

- **PHP Version**: 8.1+ (uses readonly properties, union types, attributes)
- **Strict Types**: Always use `declare(strict_types=1);`
- **Type Hints**: All parameters and return types must be typed
- **PSR-12**: Follow PSR-12 coding standards
- **Attributes**: Use PHP 8.1+ attributes for metadata (not annotations)

### Testing Patterns

Tests use **Prophecy** for mocking (via `ProphecyTrait`). Key patterns:

**Creating mock PropertyAccessor:**
```php
$accessor = $this->prophesize(PropertyAccessorInterface::class);
$accessor->getValue($source, 'prop')->willReturn('value');
$accessor->setValue(Argument::type('object'), 'prop', 'value')->shouldBeCalled();
```

**Creating mock MetadataReader:**
```php
$metadataReader = $this->createMockMetadataReader(
    $source,
    $target,
    sourceMappings: [new PropertyMapping('src', 'target')],
    sourceIgnored: ['ignored'],
    targetMappings: [new PropertyMapping('target', 'src')],
    targetIgnored: []
);
```

**Data providers** are used for parameterized tests (see `propertyReadExceptionProvider`, `nonInstantiableClassProvider`).

### Exception Design

Use static factory methods on `MappingException`:

```php
MappingException::cannotCreateInstance($className, $reason)
MappingException::invalidTargetType($target)
MappingException::propertyAccessError($class, $property, $reason)
```

This provides consistent error messages and easier testing.

### Performance Considerations

- **Metadata is cached** on first read (~10ms), subsequent lookups are ~0.1ms
- **Reflection is expensive** - always cache `ReflectionClass` instances
- **Dual indexes** use associative arrays for O(1) lookups in both directions:
  - `findTargetProperty()`: O(1) via forward index
  - `findSourceProperty()`: O(1) via reverse index
  - `isPropertyIgnored()`: O(1) via associative array
- **PropertyAccessor** overhead is ~60-80% of total mapping time
- Target object creation uses `newInstanceWithoutConstructor()` to skip constructor overhead
- **Duplicate validation** happens during `addMapping()` with minimal overhead

### Key Implementation Details

**#[Mappable] attribute requirement:**
- **REQUIRED** on both source AND target classes for mapping to work
- Provides explicit opt-in control - prevents accidental mapping of domain objects
- Type safety mechanism: ensures only classes designed for mapping participate
- Class-level attribute (not property-level): `#[Mappable]` above the class definition
- Validation happens at mapping time in `Mapper::map()`:
  - Checks `$sourceMetadata->isMappable` and `$targetMetadata->isMappable`
  - Throws `MappingException::notMappable()` if either class lacks the attribute
- MetadataReader behavior when class lacks `#[Mappable]`:
  - Sets `$metadata->isMappable = false`
  - Skips property processing entirely (optimization)
  - Returns empty metadata (no mappings, no ignored properties)
- Example error message: `Class "UserDTO" cannot be used as source for mapping. Add #[Mappable] attribute to the class to enable mapping.`

**Why skip read errors but throw on write errors?**
- Source objects may be partially populated (uninitialized properties are valid)
- Target mappings should be well-defined (write failures indicate configuration issues)

**Why use `newInstanceWithoutConstructor()`?**
- Avoids requiring default constructors
- Prevents dependency injection issues
- Allows mapping to entities with complex construction logic

**Why cache ReflectionClass in metadata?**
- Creating `ReflectionClass` is expensive (~5ms per class)
- Reflection data is immutable once class is loaded
- Cached instance is reused for `hasProperty()` checks

**Array mapping implementation:**
- `#[MapArray(TargetClass::class)]` attribute specifies target element class
- `ArrayMapping` metadata stores property name and target element class
- `Mapper::mapArray()` recursively maps each array element using `map()`
- Array keys are preserved (works with associative and indexed arrays)
- Non-object values in arrays are preserved as-is (scalars, null, nested arrays)
- Symmetrical mapping works automatically in both directions
- Array mappings stored in `MappingMetadata::$arrayMappings` with O(1) lookup

## Common Development Tasks

### Creating Mappable Classes

When creating new classes for use with Simmap:

1. **Always add `#[Mappable]` attribute** to the class (required for both DTOs and entities)
2. Import the attribute: `use Alecszaharia\Simmap\Attribute\Mappable;`
3. Add property-level attributes as needed: `#[MapTo]`, `#[MapArray]`, `#[Ignore]`

Example:
```php
use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable]
class UserDTO {
    public string $name;
    public string $email;
}
```

**Important**: Both source AND target classes need `#[Mappable]` for mapping to work

### Adding a New Attribute

1. Create attribute class in `src/Attribute/`
2. Update `MetadataReader::processProperty()` to scan for new attribute
3. Update `MappingMetadata` to store the attribute data
4. Update `Mapper::resolveTargetPropertyPath()` if it affects property resolution
5. Add tests in `tests/Unit/`

### Modifying Mapping Logic

1. Change logic in `Mapper::resolveTargetPropertyPath()` or `Mapper::map()`
2. Ensure symmetrical mapping still works (test both directions)
3. Update performance-sensitive code carefully (profile if needed)
4. Add test cases covering the new logic

### Adding Exception Types

1. Add static factory method to `MappingException`
2. Throw from appropriate location in `Mapper`
3. Add test verifying exception is thrown with correct message
4. Update documentation in `docs/troubleshooting.md`

## Documentation

Comprehensive documentation is in the `docs/` directory:

- **architecture.md**: Deep dive into design decisions and component interactions
- **performance.md**: Benchmarking, optimization strategies, profiling
- **troubleshooting.md**: Common issues with solutions and debugging techniques
- **examples.md**: Real-world use cases (e-commerce, API mapping, CQRS, etc.)

When making architectural changes, update the relevant documentation files.