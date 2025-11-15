# Simmap - Symmetrical Object Mapper

A powerful PHP object mapper library with attribute-based configuration for Symfony projects. Maps DTOs to Entities (or any other classes) with support for symmetrical bidirectional mapping and nested property paths.

## Features

- **Attribute-based Configuration**: Use PHP 8.1+ attributes to define mappings
- **Symmetrical Mapping**: Same metadata works in both directions (A→B and B→A)
- **Nested Properties**: Support for mapping to/from nested properties like `user.address.city`
- **Auto-mapping**: Automatically maps properties with matching names
- **PropertyAccess Integration**: Leverages Symfony's PropertyAccess component for robust property access
- **Type Safe**: Full PHP 8.1+ type hints and strict types

## Requirements

- PHP >= 8.1
- Symfony PropertyAccess component ^6.0 or ^7.0

## Installation

```bash
composer require alecszaharia/simmap
```

## Basic Usage

### Auto-mapping (Properties with Same Names)

```php
use Alecszaharia\Simmap\Mapper;

class UserDTO {
    public string $name;
    public string $email;
}

class UserEntity {
    public string $name;
    public string $email;
}

$dto = new UserDTO();
$dto->name = 'John Doe';
$dto->email = 'john@example.com';

$mapper = new Mapper();
$entity = $mapper->map($dto, UserEntity::class);
```

### Custom Property Mapping

Use the `#[MapTo]` attribute to map properties with different names:

```php
use Alecszaharia\Simmap\Attribute\MapTo;
use Alecszaharia\Simmap\Mapper;

class ProductDTO {
    public string $productName;

    #[MapTo('quantity')]
    public int $stock;
}

class ProductEntity {
    public string $productName;
    public int $quantity;
}

$mapper = new Mapper();
$entity = $mapper->map($dto, ProductEntity::class);
```

### Symmetrical Mapping

The mapper works bidirectionally - you can map in reverse without additional configuration:

```php
// Forward: DTO → Entity
$entity = $mapper->map($dto, ProductEntity::class);

// Reverse: Entity → DTO (uses the same mapping metadata)
$dto = $mapper->map($entity, ProductDTO::class);
```

### Nested Property Mapping

Map flat properties to nested object structures:

```php
use Alecszaharia\Simmap\Attribute\MapTo;

class PersonDTO {
    public string $name;

    #[MapTo('address.city')]
    public string $city;

    #[MapTo('address.country')]
    public string $country;
}

class Address {
    public string $city;
    public string $country;
}

class PersonEntity {
    public string $name;
    public Address $address;

    public function __construct() {
        $this->address = new Address();
    }
}

$dto = new PersonDTO();
$dto->name = 'Jane Smith';
$dto->city = 'New York';
$dto->country = 'USA';

$entity = $mapper->map($dto, PersonEntity::class);
// $entity->address->city === 'New York'
// $entity->address->country === 'USA'
```

### Ignoring Properties

Use `#[Ignore]` to exclude properties from mapping:

```php
use Alecszaharia\Simmap\Attribute\Ignore;

class OrderDTO {
    public int $orderId;
    public float $total;

    #[Ignore]
    public string $tempData; // This won't be mapped
}
```

### Array Mapping

Use `#[MapArray]` to automatically map arrays of objects:

```php
use Alecszaharia\Simmap\Attribute\MapArray;
use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable]
class OrderItemDTO {
    public string $productName;
    public int $quantity;
}

#[Mappable]
class OrderItem {
    public string $productName;
    public int $quantity;
}

#[Mappable]
class OrderDTO {
    public int $orderId;

    #[MapArray(OrderItem::class)]
    public array $items = [];
}

#[Mappable]
class Order {
    public int $orderId;

    #[MapArray(OrderItemDTO::class)]
    public array $items = [];
}

// Each item in the array is automatically mapped
$order = $mapper->map($orderDto, Order::class);
```

Features:
- **Automatic element mapping**: Each array element is recursively mapped to the target class
- **Preserves keys**: Works with both indexed and associative arrays
- **Symmetrical**: Works in both directions (bidirectional)
- **Can be combined with `#[MapTo]`**: Change both property name and element type

## Advanced Usage

### Mapping to Existing Objects

Instead of passing a class name, you can pass an existing object instance:

```php
$existingEntity = new UserEntity();
$mapper->map($dto, $existingEntity);
```

### Symfony Integration

The mapper can be registered as a service in Symfony:

```yaml
# config/services.yaml
services:
    Alecszaharia\Simmap\Mapper:
        arguments:
            $propertyAccessor: '@property_accessor'
```

### Custom PropertyAccessor

Inject a custom PropertyAccessor for advanced configuration:

```php
use Symfony\Component\PropertyAccess\PropertyAccess;

$propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
    ->enableExceptionOnInvalidIndex()
    ->getPropertyAccessor();

$mapper = new Mapper($propertyAccessor);
```

## Exception Handling

The mapper throws `MappingException` in the following scenarios:

### Invalid Target Type

```php
use Alecszaharia\Simmap\Exception\MappingException;

try {
    $mapper->map($dto, 123); // Invalid: not an object or class name
} catch (MappingException $e) {
    // "Invalid target type "integer". Target must be an object instance, a class name string, or null."
}
```

### Cannot Create Instance

```php
interface UserInterface {}

try {
    $mapper->map($dto, UserInterface::class); // Cannot instantiate interface
} catch (MappingException $e) {
    // "Cannot create instance of class "UserInterface": Class is not instantiable (abstract or interface)"
}
```

### Property Access Errors

```php
class ReadOnlyEntity {
    private string $id;

    public function getId(): string {
        return $this->id;
    }
    // No setter - property is read-only
}

try {
    $mapper->map($dto, ReadOnlyEntity::class);
} catch (MappingException $e) {
    // "Cannot access property "id" on class "ReadOnlyEntity": ..."
}
```

### Graceful Handling

The mapper gracefully handles:
- **Uninitialized properties**: Skipped during mapping (no exception)
- **Missing properties**: Auto-skipped if no mapping defined
- **Inaccessible properties**: Read errors are silently skipped; write errors throw exception

```php
class PartialDTO {
    public string $name; // Not initialized
    public string $email = 'test@example.com';
}

$dto = new PartialDTO();
// $dto->name is uninitialized - will be skipped
$entity = $mapper->map($dto, UserEntity::class);
// Only 'email' is mapped, 'name' is skipped without error
```

## How It Works

1. **Metadata Extraction**: The mapper uses PHP Reflection to read attributes from source and target classes
2. **Property Resolution**: For each source property, it determines the target property path by:
   - Checking for explicit `#[MapTo]` attribute on source
   - Checking for reverse mapping in target metadata (symmetrical mapping)
   - Auto-mapping if property names match and target has the property
3. **Value Transfer**: Uses Symfony's PropertyAccess component to read from source and write to target, supporting nested paths

## Testing

The project includes a Makefile for running tests:

```bash
# Run all tests
make test

# Run specific test file
make test-file FILE=tests/Unit/MapperTest.php

# Run specific test method
make test-filter FILTER=testMethodName

# Run with coverage report
make test-coverage

# Show all available commands
make help
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for more details on development workflow.

## API Reference

### Mapper

```php
public function map(object $source, object|string|null $target = null): object
```

- `$source`: Source object to map from
- `$target`: Target object instance, class name string, or null
- Returns: The mapped target object

### Attributes

#### `#[MapTo(string $targetProperty)]`

Maps a property to a different property path in the target object.

```php
#[MapTo('user.profile.displayName')]
public string $name;
```

#### `#[Ignore]`

Excludes a property from automatic mapping.

```php
#[Ignore]
public string $internalData;
```

#### `#[MapArray(class-string $targetClass)]`

Maps an array property with automatic element type conversion.

```php
#[MapArray(OrderItem::class)]
public array $items = [];
```

Can be combined with `#[MapTo]`:

```php
#[MapArray(OrderItem::class)]
#[MapTo('orderItems')]
public array $items = [];
```

## Examples

See the `examples/BasicUsage.php` file for comprehensive examples covering all features.

For advanced examples and real-world use cases, check the [Examples Guide](docs/examples.md).

## Documentation

Comprehensive documentation is available in the `docs/` directory:

- **[Architecture](docs/architecture.md)** - Internal design and implementation details
- **[Performance](docs/performance.md)** - Benchmarking and optimization guide
- **[Troubleshooting](docs/troubleshooting.md)** - Common issues and solutions
- **[Examples](docs/examples.md)** - Advanced use cases and patterns

See the [Documentation Index](docs/README.md) for the complete guide.

## License

MIT

## Contributing

Contributions are welcome! Please submit pull requests with tests.
