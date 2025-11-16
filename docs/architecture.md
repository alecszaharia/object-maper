# Architecture

This document describes the internal architecture and design decisions of Simmap.

## Overview

Simmap is built around a simple but powerful architecture with three main components:

```
┌─────────────────────────────────────────────┐
│              Mapper                         │
│  (Orchestrates mapping operations)          │
└──────────────┬──────────────────────────────┘
               │
        ┌──────┴──────┐
        │             │
┌───────▼──────┐  ┌───▼──────────────────┐
│ MetadataReader│  │ PropertyAccessor     │
│ (Reads attrs) │  │ (Symfony component)  │
└───────────────┘  └──────────────────────┘
```

## Components

### 1. Mapper (`src/Mapper.php`)

**Responsibility**: Orchestrates the mapping process

**Key Features**:
- Implements `MapperInterface` for extensibility
- Manages property value transfer between objects
- Handles object instantiation for string class names
- Coordinates metadata reading and property access

**Design Decisions**:
- Uses Symfony PropertyAccess for robust property operations
- Gracefully handles read errors (skip), strict on write errors (throw)
- Supports both class names and object instances as targets
- Creates instances without calling constructors (avoids dependency issues)

### 2. MetadataReader (`src/Metadata/MetadataReader.php`)

**Responsibility**: Reads and caches mapping metadata from PHP attributes

**Key Features**:
- Scans classes for `#[MapTo]` and `#[Ignore]` attributes
- Caches reflection data and metadata per class
- Builds optimized lookup indexes for symmetrical mapping

**Performance Optimizations**:
- **Caching**: Metadata is read once per class and cached
- **Reflection Cache**: `ReflectionClass` instances cached to avoid re-creation
- **O(1) Lookups**: Uses dual associative arrays for both forward and reverse lookups
- **Duplicate Prevention**: Validates uniqueness of source and target properties

**Metadata Structure**:
```php
MappingMetadata {
    reflection: ReflectionClass         // Cached reflection
    propertyMappings: array             // Array of PropertyMapping objects
    arrayMappings: array                // Array of ArrayMapping objects
    forwardMappingIndex: array          // source -> target index (O(1) lookup)
    reverseMappingIndex: array          // target -> source index (O(1) lookup)
    ignoredProperties: array            // Associative array for O(1) checks
}
```

**Duplicate Handling**:
- Throws `InvalidArgumentException` if duplicate source property is added
- Throws `InvalidArgumentException` if duplicate target property is added
- Silently skips duplicate ignored properties

### 3. PropertyMapping (`src/Metadata/PropertyMapping.php`)

**Responsibility**: Represents a single property mapping

**Structure**:
```php
PropertyMapping {
    sourceProperty: string    // Source property name
    targetProperty: string    // Target property path (supports nesting)
}
```

### 4. ArrayMapping (`src/Metadata/ArrayMapping.php`)

**Responsibility**: Represents an array property mapping with element type transformation

**Structure**:
```php
ArrayMapping {
    propertyName: string      // Property name containing the array
    targetElementClass: string // Fully qualified class name for array elements
}
```

**Key Features**:
- Enables automatic mapping of array elements from one type to another
- Preserves array keys (both indexed and associative arrays)
- Works symmetrically - bidirectional with single attribute definition
- Recursively maps each object in the array using `Mapper::map()`
- Non-object values (scalars, null, nested arrays) are preserved as-is

### 5. Attributes

#### `#[MapTo]` (`src/Attribute/MapTo.php`)
- Marks explicit property mappings
- Supports nested property paths
- Works symmetrically (bidirectional)
- Can be combined with `#[MapArray]` for renaming array properties

#### `#[MapArray]` (`src/Attribute/MapArray.php`)
- Specifies target class for array element mapping
- Automatically maps each array element to the specified type
- Works symmetrically in both directions
- Preserves array keys (indexed and associative)
- Can be combined with `#[MapTo]` to both rename property and map elements

#### `#[Ignore]` (`src/Attribute/Ignore.php`)
- Excludes properties from auto-mapping
- Applied at property level

#### `#[Mappable]` (`src/Attribute/Mappable.php`)
- **Required** - Marks classes as eligible for mapping
- Both source and target classes must have this attribute
- Provides explicit opt-in control over which classes can participate in mapping
- Prevents accidental mapping of classes not designed for it
- Throws `MappingException::notMappable()` if either class is missing this attribute

## Mapping Algorithm

The mapping process follows this algorithm:

```
1. Resolve target object
   ├─ If string: Instantiate class (without constructor)
   └─ If object: Use as-is

2. Get metadata for both source and target
   └─ Cache hit: Return cached metadata
   └─ Cache miss: Read attributes, build metadata, cache

3. Validate #[Mappable] attribute
   ├─ Check source class has #[Mappable]
   ├─ Check target class has #[Mappable]
   └─ Throw MappingException if either is missing

4. For each source property:
   ├─ Skip if ignored in source metadata
   ├─ Check if property has #[MapArray] attribute
   │  └─ If yes: Map each array element to target class
   ├─ Resolve target property path:
   │  ├─ Check explicit #[MapTo] on source
   │  ├─ Check reverse mapping in target (symmetrical)
   │  └─ Auto-map if same name exists on target
   ├─ Read value from source (skip on error)
   └─ Write value to target (throw on error)

5. Return mapped target object
```

## Symmetrical Mapping

The "symmetrical" aspect is achieved through **dual index lookups** - both forward and reverse indexes provide O(1) performance:

```php
// DTO class
#[Mappable]
class UserDTO {
    #[MapTo('fullName')]
    public string $name;
}

// Entity class
#[Mappable]
class UserEntity {
    public string $fullName;
}
```

**Forward mapping (DTO → Entity)**:
- `name` has `#[MapTo('fullName')]` → uses forward index → maps to `fullName`

**Reverse mapping (Entity → DTO)**:
- `fullName` checks reverse index → finds `name` mapping to it
- Maps `fullName` → `name` automatically!

This is implemented via dual indexes in `MappingMetadata`:

```php
// Built during metadata reading
$metadata->forwardMappingIndex = [
    'name' => 'fullName'  // source -> target (O(1))
];

$metadata->reverseMappingIndex = [
    'fullName' => 'name'  // target -> source (O(1))
];
```

Both directions now have constant-time performance, eliminating the previous O(n) linear search for forward lookups.

## Property Access Integration

Simmap leverages Symfony's PropertyAccess component for:

1. **Flexible Property Access**:
   - Public properties
   - Getters/setters
   - Magic methods (`__get`, `__set`)
   - Array-like access

2. **Nested Path Support**:
   ```php
   $accessor->getValue($obj, 'address.city.name')
   ```

3. **Type Safety**:
   - Validates property existence
   - Checks writability before writing

## Error Handling Strategy

### Read Errors (Graceful)
- Uninitialized properties → Skip
- Missing properties → Skip
- Access denied → Skip

**Rationale**: Source object may be partially populated

### Write Errors (Strict)
- Property doesn't exist → Exception
- Not writable → Exception
- Type mismatch → Exception

**Rationale**: Target mapping should be well-defined

## Performance Considerations

### 1. Metadata Caching
- First mapping: ~N milliseconds (reflection + attribute reading)
- Subsequent mappings: ~0.1 milliseconds (cache hit)

### 2. Memory Usage
- Metadata cached per class (not per instance)
- Memory overhead: ~1-5 KB per mapped class

### 3. Mapping Speed
- Simple mapping (10 properties): ~0.5 ms
- Nested mapping (10 properties): ~1 ms
- Primarily limited by PropertyAccess overhead

### Optimization Tips

1. **Reuse Mapper Instances**: Share one mapper across application
2. **Batch Operations**: Map multiple objects in sequence (cache warm)
3. **Avoid Lazy Loading**: Ensure source properties are initialized
4. **Minimize Nesting**: Deep paths (`a.b.c.d.e`) are slower

## Extension Points

### Custom PropertyAccessor

```php
use Symfony\Component\PropertyAccess\PropertyAccessorBuilder;

$accessor = (new PropertyAccessorBuilder())
    ->enableExceptionOnInvalidIndex()
    ->enableMagicCall()
    ->getPropertyAccessor();

$mapper = new Mapper($accessor);
```

### Custom MetadataReader

```php
class CachedMetadataReader extends MetadataReader {
    // Implement persistent caching (Redis, APCu, etc.)
}

$mapper = new Mapper(null, new CachedMetadataReader());
```

## Design Patterns

1. **Strategy Pattern**: PropertyAccessor is injectable
2. **Cache Pattern**: Metadata caching for performance
3. **Builder Pattern**: Reflection-based object construction
4. **Registry Pattern**: Metadata registry with cache

## Array Mapping Implementation

The `#[MapArray]` attribute enables automatic collection mapping:

**How It Works**:
1. `MetadataReader` scans for `#[MapArray]` attributes during metadata reading
2. Stores `ArrayMapping` objects in `MappingMetadata::$arrayMappings`
3. During mapping, `Mapper::map()` checks if property has array mapping
4. For each element in source array:
   - If element is an object: recursively call `Mapper::map()` with target class
   - If element is scalar/null/array: preserve as-is
5. Array keys are preserved (works with both indexed and associative arrays)

**Symmetrical Behavior**:
```php
// Source → Target
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

// Forward mapping: OrderDTO → Order (maps each OrderItemDTO → OrderItem)
// Reverse mapping: Order → OrderDTO (maps each OrderItem → OrderItemDTO)
```

The reverse index automatically handles array mappings, making them fully bidirectional.

## Future Enhancements

Potential areas for extension:

- [ ] Custom transformers (e.g., date formatting)
- [ ] Conditional mapping (e.g., `#[MapWhen]`)
- [ ] Circular reference detection
- [ ] Mapping profiles/contexts
- [ ] Event hooks (before/after mapping)

## Thread Safety

**Current Status**: Not thread-safe for metadata cache writes

**Recommendation**:
- Single-threaded PHP: No issues
- Multi-threaded environments: Consider external cache with locking

## Testing Architecture

Tests are organized by component:

```
tests/Unit/
├── MapperTest.php                     # Core mapping functionality
└── Metadata/
    └── MappingMetadataTest.php       # Metadata storage, indexes, duplicates
```

**Test Coverage**:
- `MapperTest.php`: 18 tests covering mapping operations, error handling, symmetrical mapping
- `MappingMetadataTest.php`: 25 tests covering indexes, duplicates, performance, edge cases

Total: 43 tests, 112 assertions

## Contributing to Architecture

When proposing architectural changes:

1. Maintain backward compatibility
2. Consider performance impact
3. Add benchmarks for new features
4. Update this document
5. Add integration tests

See [CONTRIBUTING.md](../CONTRIBUTING.md) for details.
