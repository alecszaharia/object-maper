<objective>
Extend the Simmap object mapper to support mapping arrays and collections of objects using the existing #[MapTo] attribute. This feature must work bidirectionally (SourceDTO[] ↔ TargetEntity[]) and integrate seamlessly with the current symmetrical mapping implementation without breaking existing functionality.

This enhancement will allow users to map collections of objects using the same attribute pattern they already use for single properties, maintaining API consistency and enabling powerful array transformations.
</objective>

<context>
This is a PHP 8.2+ symmetrical object mapper library that uses attributes for configuration. The mapper currently supports single object mapping with the #[MapTo] attribute.

Current project structure:
- Mapper class handles the core mapping logic
- Attributes (#[MapTo], #[MapArray], #[Mappable], #[Ignore]) configure mapping behavior
- MetadataReader extracts mapping configuration from attributes
- Uses Symfony PropertyAccess component for getting/setting values

Read and thoroughly analyze these files to understand the current implementation:
- @src/Mapper.php - Core mapping logic and algorithms
- @src/Attribute/MapTo.php - The attribute that will be extended for arrays
- @src/Metadata/MetadataReader.php - How attributes are parsed and metadata is built
- @src/Metadata/MappingMetadata.php - Metadata structure
- @src/Metadata/PropertyMapping.php - Individual property mapping configuration
- @tests/Unit/MapperTest.php - Existing test patterns and fixtures

Understanding the current implementation is critical to maintaining consistency and ensuring the array mapping integrates seamlessly.
</context>

<requirements>

1. **Type Inference from MapTo Attribute**
   - When a property has #[MapTo(TargetClass::class)] and contains an iterable collection, automatically map each item to TargetClass
   - Support all iterable types: arrays, ArrayObject, iterators, any traversable collection
   - Preserve the collection type when possible (array remains array, ArrayObject remains ArrayObject)

2. **Bidirectional Symmetry**
   - Array mapping must work in both directions, just like single object mapping
   - When mapping A → B with arrays, the reverse B → A must work automatically
   - Maintain the same symmetrical behavior that exists for single properties

3. **Transparent Extension**
   - No breaking changes to existing API or attribute usage
   - Same #[MapTo] attribute works for both single objects and arrays
   - Mapper auto-detects if property is a collection and handles accordingly
   - Existing single-object mapping continues to work exactly as before

4. **Error Handling**
   - Throw meaningful exceptions on the first problem encountered
   - Do NOT skip invalid items or collect errors
   - Provide clear error messages indicating which array item failed and why
   - Include context: property name, item index, source/target classes

5. **Comprehensive Edge Cases**
   - Empty arrays (should map to empty arrays)
   - Null values (both null arrays and null items within arrays)
   - Nested arrays (arrays of objects that themselves contain arrays)
   - Mixed types in arrays (detect and throw clear error)
   - Circular references (detect and prevent infinite loops)
   - Large arrays (reasonable performance, avoid memory issues)
   - Type mismatches (source item can't map to target type)

6. **Production Quality**
   - Full PHPDoc blocks for all new/modified methods
   - Inline comments explaining complex logic, especially around type detection and recursion
   - Proper type hints on all parameters and return types
   - Follow existing code style and patterns from the codebase
   - Consider memory efficiency - don't load entire large arrays unnecessarily

7. **Testing Requirements**
   - Add comprehensive unit tests to existing test suite
   - Update existing test fixtures (test classes/DTOs) to include array properties
   - Test cases must cover:
     * Basic array mapping (both directions)
     * Empty arrays
     * Null handling
     * Nested arrays
     * Error conditions (mixed types, unmappable items)
     * Large arrays (performance verification)
   - Follow existing test patterns from MapperTest.php
</requirements>

<implementation>

**Analysis Phase:**
1. Thoroughly read and understand the current implementation files listed in <context>
2. Identify where array detection and handling logic should be added
3. Understand how PropertyAccess gets/sets values and how to work with collections
4. Map out the symmetrical mapping strategy for arrays

**Development Approach:**
1. Extend MetadataReader to detect when a property with #[MapTo] is an array/collection type
2. Modify Mapper's core mapping logic to handle collections recursively
3. Implement bidirectional array mapping that mirrors single-object behavior
4. Add comprehensive error handling with clear, actionable messages
5. Handle all edge cases systematically with proper validation

**What to Avoid (and WHY):**
- Don't create new attributes for arrays - use existing #[MapTo] to maintain API simplicity
- Don't skip errors or collect them - fail fast for data integrity (user requested immediate failure)
- Don't break existing functionality - all current tests must still pass
- Don't hardcode array type checking - support all iterables for flexibility
- Don't ignore performance - use generators or chunking if needed for very large arrays
- Don't assume array items are objects - validate and provide clear errors if not mappable

**Patterns to Follow:**
- Use the same recursive mapping strategy that works for nested single objects
- Mirror the bidirectional symmetry pattern from single-object mapping
- Follow existing exception patterns for consistency
- Use PropertyAccess component consistently with current implementation
- Maintain the same metadata structure and extension points
</implementation>

<output>
Modify existing files:
- `./src/Mapper.php` - Add array mapping logic to core mapper
- `./src/Metadata/MetadataReader.php` - Enhance to detect and handle array properties
- `./src/Metadata/PropertyMapping.php` - Add array-specific metadata if needed
- `./tests/Unit/MapperTest.php` - Add comprehensive array mapping tests
- Update test fixture classes in tests to include array properties with #[MapTo]

Create new files only if absolutely necessary for clean separation of concerns.

All code must include:
- PHPDoc blocks with @param, @return, @throws annotations
- Inline comments explaining the "why" behind complex logic
- Proper type hints (PHP 8.2+ strict types)
</output>

<verification>
Before declaring complete, verify your implementation:

1. **Run existing tests**: All current unit tests must pass without modification
   - Execute: `!vendor/bin/phpunit tests/Unit/MapperTest.php`

2. **Run new array tests**: Your new test cases must all pass
   - Verify bidirectional mapping works for arrays
   - Verify error handling throws on first problem
   - Verify edge cases are handled correctly

3. **Code review checklist**:
   - [ ] No breaking changes to existing API
   - [ ] Same #[MapTo] attribute works for single objects and arrays
   - [ ] Bidirectional array mapping works correctly
   - [ ] Comprehensive error messages on failures
   - [ ] All edge cases have test coverage
   - [ ] PHPDoc and inline comments are complete
   - [ ] Performance is reasonable for large arrays

4. **Integration check**:
   - [ ] Updated test fixtures demonstrate real-world usage
   - [ ] Array mapping integrates seamlessly with existing features
   - [ ] No regression in single-object mapping behavior
</verification>

<success_criteria>
1. The #[MapTo] attribute successfully maps arrays of objects bidirectionally
2. All iterable collection types are supported (arrays, ArrayObject, iterators)
3. Type inference works correctly from the MapTo attribute parameter
4. Transparent extension - existing code continues to work without changes
5. First error encountered throws a clear, actionable exception
6. All edge cases (empty arrays, nulls, nested arrays, circular refs, large arrays) are handled
7. Comprehensive unit tests demonstrate functionality and prevent regressions
8. All existing tests continue to pass
9. Code includes complete PHPDoc blocks and inline comments
10. Performance is balanced - no obvious inefficiencies for large arrays
</success_criteria>
