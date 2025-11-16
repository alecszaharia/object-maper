# Troubleshooting Guide

This guide covers common issues, error messages, and their solutions.

## Common Issues

### Property Not Being Mapped

**Symptom**: A property value is not transferred from source to target

#### Cause 1: Property is Uninitialized

```php
#[Mappable]
class UserDTO {
    public string $name; // Not initialized!
}

$dto = new UserDTO();
$entity = $mapper->map($dto, UserEntity::class);
// $entity->name is not set
```

**Solution**: Initialize the property
```php
$dto = new UserDTO();
$dto->name = 'John'; // Initialize before mapping
```

Or use default values:
```php
#[Mappable]
class UserDTO {
    public string $name = ''; // Default value
}
```

#### Cause 2: Property is Ignored

```php
#[Mappable]
class UserDTO {
    #[Ignore]
    public string $name;
}
```

**Solution**: Remove `#[Ignore]` attribute or map a different property

#### Cause 3: Target Property Doesn't Exist

```php
#[Mappable]
class SourceDTO {
    public string $userName; // Source has 'userName'
}

#[Mappable]
class TargetEntity {
    public string $name; // Target has 'name' (different!)
}
```

**Solution**: Add explicit mapping
```php
#[Mappable]
class SourceDTO {
    #[MapTo('name')]
    public string $userName;
}
```

#### Cause 4: Property is Not Writable

```php
#[Mappable]
class TargetEntity {
    private string $name; // No setter!
}
```

**Solution**: Add a setter or make property public
```php
#[Mappable]
class TargetEntity {
    private string $name;

    public function setName(string $name): void {
        $this->name = $name;
    }
}
```

### Nested Property Not Working

**Symptom**: Error or null value when mapping to nested path

```php
#[Mappable]
class PersonDTO {
    #[MapTo('address.city')]
    public string $city;
}

#[Mappable]
class Person {
    public ?Address $address = null; // NULL!
}
```

**Problem**: Target nested object is not initialized

**Solution**: Initialize nested objects in constructor
```php
#[Mappable]
class Person {
    public Address $address;

    public function __construct() {
        $this->address = new Address(); // Initialize!
    }
}
```

### MappingException: Class Not Mappable

**Error Message**:
```
Class "App\DTO\UserDTO" cannot be used as source for mapping.
Add #[Mappable] attribute to the class to enable mapping.
```

**Cause**: The source or target class is missing the required `#[Mappable]` attribute

**Solution**: Add `#[Mappable]` to both source and target classes
```php
use Alecszaharia\Simmap\Attribute\Mappable;

// ❌ Don't - Missing attribute
class UserDTO {
    public string $name;
}

// ✅ Do - Has required attribute
#[Mappable]
class UserDTO {
    public string $name;
}

#[Mappable]
class UserEntity {
    public string $name;
}

$mapper->map($dto, UserEntity::class); // Now works!
```

This is a **required** security feature that provides explicit opt-in control over which classes can participate in mapping operations.

### MappingException: Cannot Create Instance

**Error Message**:
```
Cannot create instance of class "App\Entity\UserInterface":
Class is not instantiable (abstract or interface)
```

**Cause**: Trying to map to an interface or abstract class

**Solution**: Map to a concrete class
```php
// ❌ Don't
$mapper->map($dto, UserInterface::class);

// ✅ Do
$mapper->map($dto, ConcreteUser::class);
```

Or pass an existing instance:
```php
$user = new ConcreteUser();
$mapper->map($dto, $user);
```

### MappingException: Invalid Target Type

**Error Message**:
```
Invalid target type "integer".
Target must be an object instance, a class name string, or null.
```

**Cause**: Passing invalid type as target

**Solution**: Pass object or class name string
```php
// ❌ Don't
$mapper->map($dto, 123);
$mapper->map($dto, true);
$mapper->map($dto, ['class' => Entity::class]);

// ✅ Do
$mapper->map($dto, Entity::class);      // Class name string
$mapper->map($dto, new Entity());      // Object instance
```

### MappingException: Property Access Error

**Error Message**:
```
Cannot access property "readOnly" on class "App\Entity\User":
The property "readOnly" in class "App\Entity\User" is not writable.
```

**Cause**: Target property cannot be written to

**Solutions**:

1. Add a setter method:
```php
#[Mappable]
class User {
    private string $readOnly;

    public function setReadOnly(string $value): void {
        $this->readOnly = $value;
    }
}
```

2. Make property public:
```php
#[Mappable]
class User {
    public string $readOnly;
}
```

3. Use `#[Ignore]` on source if property shouldn't be mapped:
```php
#[Mappable]
class DTO {
    #[Ignore]
    public string $readOnly;
}
```

### Symmetrical Mapping Not Working

**Symptom**: Forward mapping works, but reverse doesn't

```php
#[Mappable]
class UserDTO {
    #[MapTo('fullName')]
    public string $name;
}

#[Mappable]
class UserEntity {
    public string $fullName;
}

// Forward: Works
$entity = $mapper->map($dto, UserEntity::class);

// Reverse: Doesn't work?
$dto = $mapper->map($entity, UserDTO::class);
```

**Cause**: Usually works automatically, but check:

1. **Property types match**: Ensure both properties have compatible types
2. **No #[Ignore] on target**: Check target doesn't ignore the property
3. **Spelling**: Verify exact property name in `#[MapTo]`

**Debug**:
```php
// Add explicit reverse mapping if needed
#[Mappable]
class UserEntity {
    #[MapTo('name')]
    public string $fullName;
}
```

### Array Elements Not Being Mapped

**Symptom**: Array property exists but elements are not transformed

```php
#[Mappable]
class OrderDTO {
    #[MapArray(OrderItem::class)]
    public array $items = [];
}

$orderDto = new OrderDTO();
$orderDto->items = [new ItemDTO(), new ItemDTO()];

$order = $mapper->map($orderDto, Order::class);
// $order->items still contains ItemDTO instances instead of OrderItem
```

#### Cause 1: Missing #[MapArray] Attribute

**Solution**: Ensure both source and target have `#[MapArray]` with correct class
```php
#[Mappable]
class OrderDTO {
    #[MapArray(OrderItem::class)]  // ← Must specify target class
    public array $items = [];
}

#[Mappable]
class Order {
    #[MapArray(ItemDTO::class)]    // ← For reverse mapping
    public array $items = [];
}
```

#### Cause 2: Wrong Target Class Name

```php
#[MapArray(OrderItem::class)]  // ❌ Wrong class name
```

**Solution**: Use fully qualified class name
```php
use App\Entity\OrderItem;

#[MapArray(OrderItem::class)]  // ✅ Correct
// or
#[MapArray(\App\Entity\OrderItem::class)]  // ✅ Also correct
```

#### Cause 3: Missing #[Mappable] on Array Element Classes

The array element classes must also have `#[Mappable]`:

```php
#[Mappable]  // ← Required for array element classes
class ItemDTO {
    public string $name;
}

#[Mappable]  // ← Required for array element classes
class OrderItem {
    public string $name;
}
```

### Array Keys Not Preserved

**Symptom**: Associative array loses its keys

```php
$dto->items = ['sku-001' => $item1, 'sku-002' => $item2];
$entity = $mapper->map($dto, Entity::class);
// $entity->items has numeric keys [0, 1] instead of ['sku-001', 'sku-002']
```

**Cause**: This shouldn't happen - `#[MapArray]` preserves keys by default

**Debug**:
```php
// Check before mapping
var_dump(array_keys($dto->items));

// Check after mapping
var_dump(array_keys($entity->items));
```

**Solution**: If keys are lost, verify:
1. Source array actually has the keys you expect
2. No manual array manipulation happening
3. Using `#[MapArray]` (not manual array_map)

### Large Array Performance Issues

**Symptom**: Mapping is slow with large arrays

```php
#[MapArray(Item::class)]
public array $items = [];  // 10,000 items

$order = $mapper->map($orderDto, Order::class);  // Takes seconds
```

**Explanation**: Array mapping scales linearly - 10,000 items = 10,000 `map()` calls

**Solutions**:

1. **Process in chunks**:
```php
#[Mappable]
class OrderDTO {
    #[MapArray(Item::class)]
    public array $items = [];
}

$chunkSize = 1000;
foreach (array_chunk($orderDto->items, $chunkSize) as $chunk) {
    $tempDto = new OrderDTO();
    $tempDto->items = $chunk;
    $tempOrder = $mapper->map($tempDto, Order::class);
    // Process chunk
}
```

2. **Filter unnecessary items**:
```php
$orderDto->items = array_filter(
    $orderDto->items,
    fn($item) => $item->isActive()
);
$order = $mapper->map($orderDto, Order::class);
```

3. **Use lazy loading**:
```php
#[Mappable]
class Order {
    private array $itemDtos = [];

    public function setItems(array $items): void {
        $this->itemDtos = $items;  // Store DTOs
    }

    public function getItems(): array {
        // Map on-demand
        static $mapped = null;
        if ($mapped === null) {
            $mapped = array_map(
                fn($dto) => $this->mapper->map($dto, Item::class),
                $this->itemDtos
            );
        }
        return $mapped;
    }
}
```

See [Performance Guide - Optimize Array Mappings](performance.md#7-optimize-array-mappings) for more strategies.

### Mixed Array Content Behavior

**Symptom**: Confused about how non-object values are handled

```php
#[Mappable]
class MixedDTO {
    #[MapArray(Item::class)]
    public array $data = [];
}

$dto = new MixedDTO();
$dto->data = [
    new ItemDTO(),      // Object
    'string value',     // Scalar
    42,                 // Number
    null,               // Null
    ['nested']          // Array
];

$entity = $mapper->map($dto, Entity::class);
```

**Expected Behavior**:
- **Objects**: Mapped to target class
- **Scalars** (strings, numbers, bools): Preserved as-is
- **Null**: Preserved as-is
- **Nested arrays**: Preserved as-is

This is intentional - only objects are mapped, everything else passes through unchanged.

**If you need different behavior**:
```php
// Pre-filter to only objects
$dto->data = array_filter($dto->data, 'is_object');

// Or convert scalars before mapping
$dto->data = array_map(function($item) {
    if (is_string($item)) {
        return new ItemDTO(['value' => $item]);
    }
    return $item;
}, $dto->data);
```

### Null Values Being Transferred

**Symptom**: Null values overwrite existing data

```php
$existingEntity = $repository->find(1);
$existingEntity->name = 'John';

$dto = new UserDTO();
$dto->name = null;

$mapper->map($dto, $existingEntity);
// $existingEntity->name is now null!
```

**Explanation**: This is expected behavior - null is a valid value

**Solution**: Filter null values before mapping
```php
#[Mappable]
class UserDTO {
    public ?string $name = null;

    public function hasName(): bool {
        return $this->name !== null;
    }
}

// Conditional mapping
if ($dto->hasName()) {
    $mapper->map($dto, $entity);
}
```

Or use a wrapper:
```php
function mapNonNull(object $source, object $target, Mapper $mapper): void {
    $temp = $mapper->map($source, get_class($target));

    foreach (get_object_vars($temp) as $property => $value) {
        if ($value !== null) {
            $target->$property = $value;
        }
    }
}
```

## Performance Issues

### Slow First Mapping

**Symptom**: First mapping of a class takes > 50ms

**Cause**: Metadata reading with many properties

**Solution**: Warm up cache at application boot
```php
// In services.yaml or bootstrap
class CacheWarmer {
    public function __construct(private Mapper $mapper) {
        $this->warmUp();
    }

    private function warmUp(): void {
        $this->mapper->map(new UserDTO(), UserEntity::class);
        $this->mapper->map(new ProductDTO(), ProductEntity::class);
        // ... other frequently used mappings
    }
}
```

### Memory Usage Growing

**Symptom**: Memory increases with each mapping in long-running process

**Cause**: Likely not Simmap - check for:
1. Doctrine entities not being cleared
2. Objects retained in arrays/collections
3. Event listeners holding references

**Debug**:
```php
// Before mapping
$memBefore = memory_get_usage();

$mapper->map($dto, Entity::class);

// After mapping
$memAfter = memory_get_usage();
echo "Memory used: " . ($memAfter - $memBefore) . " bytes\n";
```

Simmap itself should use < 10KB per mapping.

### Slow Nested Property Access

**Symptom**: Mappings with nested properties are very slow

**Cause**: Deep nesting or lazy loading

**Solution**: Reduce nesting depth
```php
// ❌ Slow
#[Mappable]
class DTO {
    #[MapTo('user.profile.settings.preferences.theme')]
    public string $theme;
}

// ✅ Faster
#[Mappable]
class DTO {
    #[MapTo('theme')]
    public string $theme;
}
```

Or eager-load nested objects before mapping.

## Debugging Techniques

### 1. Enable Error Reporting

```php
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

### 2. Check What's Being Mapped

```php
#[Mappable]
class UserDTO {
    public string $name = 'John';
}

$source = new UserDTO();
echo "Source properties:\n";
print_r(get_object_vars($source));

$target = $mapper->map($source, UserEntity::class);
echo "Target properties:\n";
print_r(get_object_vars($target));
```

### 3. Verify Attribute Presence

```php
use Alecszaharia\Simmap\Metadata\MetadataReader;

$reader = new MetadataReader();
$metadata = $reader->getMetadata(new UserDTO());

echo "Property mappings:\n";
foreach ($metadata->propertyMappings as $mapping) {
    echo "{$mapping->sourceProperty} -> {$mapping->targetProperty}\n";
}

echo "\nIgnored properties:\n";
print_r($metadata->ignoredProperties);
```

### 4. Test PropertyAccess Separately

```php
use Symfony\Component\PropertyAccess\PropertyAccess;

$accessor = PropertyAccess::createPropertyAccessor();

#[Mappable]
class UserEntity {
    public string $name;
}

$entity = new UserEntity();

// Test writability
var_dump($accessor->isWritable($entity, 'name')); // Should be true

// Test getValue/setValue
try {
    $accessor->setValue($entity, 'name', 'John');
    echo $accessor->getValue($entity, 'name'); // Should print "John"
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### 5. Use Xdebug

Set breakpoints in:
- `Mapper::map()` - Main entry point
- `Mapper::resolveTargetPropertyPath()` - Property resolution
- `MetadataReader::getMetadata()` - Metadata reading

## Symfony-Specific Issues

### Service Not Found

**Error**: `Service "Alecszaharia\Simmap\Mapper" not found`

**Solution**: Register service in `config/services.yaml`
```yaml
services:
    Alecszaharia\Simmap\Mapper:
        arguments:
            $propertyAccessor: '@property_accessor'
```

### PropertyAccessor Not Injected

**Error**: Mapper works but doesn't respect Symfony configuration

**Solution**: Explicit service wiring
```yaml
services:
    Alecszaharia\Simmap\Mapper:
        arguments:
            $propertyAccessor: '@Symfony\Component\PropertyAccess\PropertyAccessorInterface'
```

### Doctrine Entities Not Persisting

**Problem**: Mapping works, but changes not saved

**Cause**: Mapped properties are not managed by Doctrine

**Solution**: Flush entity manager
```php
#[Mappable]
class User {
    public string $name;
}

$entity = $entityManager->find(User::class, 1);
$mapper->map($dto, $entity);
$entityManager->flush(); // Don't forget!
```

## Testing Issues

### Tests Fail in Isolation But Pass Together

**Cause**: Static metadata cache shared between tests

**Solution**: Clear cache between tests
```php
use PHPUnit\Framework\TestCase;

class MapperTest extends TestCase
{
    protected function setUp(): void {
        // Create fresh mapper for each test
        $this->mapper = new Mapper();
    }
}
```

### Mocking PropertyAccessor

```php
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class MapperTest extends TestCase
{
    use ProphecyTrait;

    public function testCustomAccessor(): void
    {
        $accessor = $this->prophesize(PropertyAccessorInterface::class);
        $accessor->getValue(/* ... */)->willReturn('value');

        $mapper = new Mapper($accessor->reveal());
        // ... test
    }
}
```

## Still Having Issues?

1. **Check the version**: Ensure you're using latest Simmap version
2. **Minimal reproduction**: Create smallest possible example
3. **Read error message carefully**: Often points to exact issue
4. **Check Symfony PropertyAccess docs**: Many issues are PropertyAccess-related
5. **Open GitHub issue**: Include:
   - Simmap version
   - PHP version
   - Symfony version (if applicable)
   - Complete error message
   - Minimal code example
   - Expected vs actual behavior

## Getting Help

- **GitHub Issues**: https://github.com/alecszaharia/simmap/issues
- **Stack Overflow**: Tag with `simmap` and `php`
- **Documentation**: Check README.md and docs/ folder

## Error Reference

| Error Message | Cause | Solution |
|---------------|-------|----------|
| Class cannot be used for mapping | Missing #[Mappable] attribute | Add #[Mappable] to source and target |
| Cannot create instance of class | Interface/abstract class | Use concrete class |
| Invalid target type | Wrong type passed | Pass object or class string |
| Cannot access property | Property not writable | Add setter or make public |
| Property not found | Property doesn't exist | Check spelling or add #[MapTo] |
| Uninitialized property | Property not set | Initialize before mapping |
| Array elements not mapped | Missing #[MapArray] | Add attribute with target class |
| Wrong array element type | Wrong class in #[MapArray] | Use correct fully qualified class name |
| Large array slow | Too many elements | Process in chunks or filter |

## Preventive Measures

To avoid common issues:

1. **Always mark classes with #[Mappable]** - Required on both source and target
2. **Always initialize properties** with default values
3. **Use strict types** (`declare(strict_types=1);`)
4. **Write tests** for your mappings
5. **Initialize nested objects** in constructors
6. **Use #[MapTo]** for clarity even when auto-mapping works
7. **Document complex mappings** with comments

Happy mapping!
