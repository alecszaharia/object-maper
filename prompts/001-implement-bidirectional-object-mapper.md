<objective>
Implement a production-ready bidirectional object mapper that uses PHP attributes for configuration, implements efficient metadata caching, and supports nested property mapping via Symfony PropertyAccessor.

This mapper will enable seamless, type-safe transformation between DTOs, entities, and domain objects with minimal boilerplate, using declarative attribute-based configuration.
</objective>

<context>
Project: simmap - A symmetrical object mapper library
Tech stack: PHP 8.1+, Symfony PropertyAccess component
Dependencies available: symfony/property-access (^5.4|^6.0|^7.0)
Testing: PHPUnit (^10.0|^11.0) with Prophecy for mocking

The mapper must implement the existing MapperInterface:
@src/MapperInterface.php

Key design principle: **Bidirectional symmetry** - if class A can map to class B, then class B can automatically map back to class A using the same metadata cache.
</context>

<requirements>

<class_mapping>
1. Only map classes annotated with `#[Mappable(targetClass: TargetClassName::class)]`
2. For mapping to occur, BOTH classes must have `#[Mappable]` attributes pointing to each other
3. Support multiple `#[Mappable]` attributes on the same class to map to different target classes
4. Create metadata cache per class pair (not per direction) - e.g., `UserDTO <-> User` shares one metadata instance
5. Auto-instantiate target class when `$target` parameter is a string class name
6. Validate that both classes are properly annotated before attempting mapping
</class_mapping>

<property_mapping>
1. Map all properties accessible via Symfony PropertyAccessor (public, protected, private with getters/setters)
2. Default behavior: Map properties with identical names automatically
3. Respect `#[IgnoreMap]` attribute to exclude specific properties from mapping
4. Support `#[MapTo(targetProperty: 'propertyName')]` for custom property name mapping
5. Support nested property paths using dot notation: `#[MapTo(targetProperty: 'address.street')]`
6. Create ONE metadata entry per property pair, usable in both directions
7. Handle type coercion gracefully (let PropertyAccessor handle conversions)
</property_mapping>

<metadata_caching>
1. Build metadata cache on first use for each class pair
2. Cache structure should contain:
   - Source class name
   - Target class name
   - Property mappings (bidirectional - each mapping works both ways)
   - Validation flags (both classes are #[Mappable])
3. Store metadata in memory for performance (one cache entry per unique class pair)
4. Design cache to be easily extendable for future mapping strategies (transformers, callables)
</metadata_caching>

<extensibility>
Design the architecture to support future extensions:
- Custom transformation functions per property
- Validation callbacks
- Pre/post mapping hooks
- Custom type converters
- Array/collection mapping strategies

Consider using strategy pattern or similar to make adding new mapping behaviors straightforward.
</extensibility>

</requirements>

<implementation>

<architecture>
Thoroughly analyze the design before implementing. Consider creating:

1. **Mapper** - Main class implementing MapperInterface
   - Coordinate the mapping process
   - Manage metadata cache
   - Handle instance creation

2. **MetadataReader** - Read and parse PHP attributes from classes
   - Extract #[Mappable] configurations
   - Build property mapping metadata
   - Detect #[IgnoreMap] and #[MapTo] attributes

3. **MappingMetadata** - Value object holding cache data per class pair
   - Store bidirectional property mappings
   - Validation state
   - Easy to extend with new fields

4. **PropertyMapping** - Value object for individual property pair metadata
   - Source property path
   - Target property path
   - Future: transformation strategy, validators

5. **Attributes** - Create the attribute classes:
   - `#[Mappable(targetClass: string)]` - Class-level, repeatable
   - `#[MapTo(targetProperty: string)]` - Property-level
   - `#[IgnoreMap]` - Property-level

6. **Custom Exception** - MappingException for clear error messages

Use strict types throughout. Leverage PHP 8.1+ features (readonly properties, constructor property promotion, enums if applicable).
</architecture>

<attribute_definitions>
Create attributes in `src/Attribute/`:
- Make `#[Mappable]` repeatable using `#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]`
- Ensure attribute constructors accept and store configuration properly
- Use constructor property promotion for clean, concise attribute classes
</attribute_definitions>

<mapping_logic>
In the Mapper class:

1. **Validate inputs**: Check both source and target are objects or target is valid class string
2. **Resolve target**: If target is string, instantiate it; if null, throw exception (target class unknown)
3. **Get or build metadata**: Check cache for class pair, build if missing
4. **Validate mappability**: Ensure both classes have matching #[Mappable] attributes
5. **Execute mapping**: Use Symfony PropertyAccessor to read from source and write to target
6. **Handle nested paths**: PropertyAccessor natively supports dot notation (e.g., 'address.street')
7. **Return target**: Return the populated target object

**Why PropertyAccessor?** It handles the complexity of accessing private properties via getters/setters, array access, and nested paths. This keeps our mapper clean and focused on configuration.

**Why metadata caching?** Reflection is expensive. Cache metadata after first read to make subsequent mappings fast.
</mapping_logic>

<error_handling>
Throw MappingException with clear messages for:
- Classes missing #[Mappable] attributes
- Non-reciprocal mappings (A maps to B, but B doesn't map to A)
- Invalid property paths
- Type mismatches that PropertyAccessor can't handle
- Instantiation failures

Include context in exceptions: which classes, which properties, what went wrong.
</error_handling>

</implementation>

<testing>

<test_strategy>
Create comprehensive PHPUnit tests in `tests/Unit/`. Use Prophecy for mocking when needed.

**Combine assertions intelligently** - don't create separate tests for every tiny scenario. Group related assertions in meaningful test methods.

**Test file structure:**
- `MapperTest.php` - Core mapping functionality
- `MetadataReaderTest.php` - Attribute reading and metadata building
- `MappingMetadataTest.php` - Metadata value object behavior (if complex logic exists)

**Use realistic test fixtures** - Create sample DTO/Entity classes with various attribute configurations to test against.
</test_strategy>

<test_coverage>
Ensure tests cover:

1. **Basic bidirectional mapping** - Same property names, both directions
2. **Custom property mapping** - #[MapTo] with different names
3. **Nested property mapping** - Dot notation paths like 'address.street'
4. **Ignored properties** - #[IgnoreMap] exclusion
5. **Multiple target classes** - One class with multiple #[Mappable] attributes
6. **Metadata caching** - Verify metadata is built once and reused
7. **Instance creation** - Passing class name string as $target
8. **Error cases** - Missing attributes, non-reciprocal mappings, invalid paths
9. **Property accessibility** - Private/protected properties via PropertyAccessor
10. **Type handling** - Different property types, null values

**Combine related assertions:**
```php
public function testBidirectionalMappingWithCustomPropertyNames(): void
{
    // Test both directions in one test
    $dto = new UserDTO();
    $dto->fullName = 'John Doe';

    $entity = $this->mapper->map($dto, User::class);
    $this->assertSame('John Doe', $entity->name); // DTO->Entity

    $dtoBack = $this->mapper->map($entity, UserDTO::class);
    $this->assertSame('John Doe', $dtoBack->fullName); // Entity->DTO
}
```

**Use descriptive test names** that clearly indicate what scenario is being tested.

**Mock sparingly** - Only mock external dependencies if absolutely necessary. Use real objects for internal components.
</test_coverage>

</testing>

<output>
Create the following files with proper namespace and strict types:

**Attributes:**
- `./src/Attribute/Mappable.php` - Repeatable class-level attribute
- `./src/Attribute/MapTo.php` - Property-level attribute for custom mapping
- `./src/Attribute/IgnoreMap.php` - Property-level attribute to exclude properties

**Core classes:**
- `./src/Mapper.php` - Main mapper implementing MapperInterface
- `./src/Metadata/MetadataReader.php` - Attribute reading and metadata building
- `./src/Metadata/MappingMetadata.php` - Cache value object for class pairs
- `./src/Metadata/PropertyMapping.php` - Value object for property pair metadata

**Exception:**
- `./src/Exception/MappingException.php` - Custom exception for mapping errors

**Tests:**
- `./tests/Unit/MapperTest.php` - Comprehensive mapper tests
- `./tests/Unit/Metadata/MetadataReaderTest.php` - Metadata reading tests
- Additional test value objects/fixtures as needed in `./tests/Fixtures/`

All files should:
- Use `declare(strict_types=1);`
- Follow PSR-12 coding standards
- Include proper docblocks for public methods
- Use type hints for all parameters and return types
</output>

<verification>
Before declaring complete, verify:

1. **Run PHPUnit tests**: All tests should pass
   ```bash
   make test
   ```

2. **Test bidirectionality manually**: Create a simple script that maps A->B and B->A to confirm symmetry

3. **Check metadata caching**: Add debug output to verify metadata is built once per class pair

4. **Validate attribute configurations**: Ensure #[Mappable] is properly repeatable and all attributes store their parameters correctly

5. **Review extensibility**: Confirm the architecture can easily accommodate future mapping strategies without major refactoring

6. **Check error messages**: Throw a few intentional errors to ensure MappingException messages are clear and actionable
</verification>

<success_criteria>
- All attribute classes created and properly configured (repeatable, correct targets)
- Mapper implements MapperInterface and handles all mapping scenarios
- MetadataReader correctly extracts and caches mapping configuration
- Metadata is cached per class pair (not per direction) for efficiency
- Bidirectional mapping works seamlessly in both directions
- Nested property paths work via dot notation
- All PHPUnit tests pass with comprehensive coverage
- Code is clean, well-documented, and follows PHP 8.1+ best practices
- Architecture is extensible for future mapping strategies
- Error handling provides clear, actionable exception messages
</success_criteria>