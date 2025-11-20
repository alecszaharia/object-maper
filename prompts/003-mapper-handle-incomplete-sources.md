<objective>
Modify the Mapper class to handle incomplete source objects intelligently. The mapper should map only initialized/defined properties from source objects, skipping undefined or null values. This allows partial object updates without overwriting existing target properties with empty values.

This change improves the mapper's flexibility for real-world scenarios where source data may be incomplete (e.g., API responses with optional fields, database query results with sparse columns, partial form submissions).
</objective>

<context>
This is a PHP object mapping library that transforms source objects/arrays into target class instances.

**Project type**: PHP object mapper utility
**Current behavior**: Maps all properties from source to target
**Desired behavior**: Skip undefined/null source properties during mapping

**Relevant files to examine**:
- @src/Mapper.php (main mapper class)
- @src/Metadata/* (metadata/property handling)
- @tests/Fixtures/* (test fixtures for mapping)
- @tests/* (existing test structure)
</context>

<requirements>
1. **Core Functionality**: Modify the Mapper to skip properties when:
   - Source property is undefined/not set
   - Source property is null
   - Result: Target properties remain unchanged if source didn't provide a value

2. **Scope**: Apply this behavior to:
   - Direct object mapping
   - Nested object mapping
   - Array/collection mapping (if applicable)

3. **Implementation Requirements**:
   - Preserve existing mapping logic for initialized properties
   - Do NOT break backward compatibility for code that relies on null/undefined overwrites (consider a config option if needed, but make smart defaults)
   - Keep the implementation clean and maintainable

4. **Testing Requirements**:
   - Add tests verifying incomplete source objects map correctly
   - Test edge cases: null, undefined, missing keys, mixed initialized/uninitialized properties
   - Ensure existing tests still pass
</requirements>

<implementation>
**Approach**:
1. Identify where property values are checked during mapping
2. Add logic to detect "initialized" vs "uninitialized" properties
3. Skip property assignment when source value is not initialized
4. For nested/collection mappings, recursively apply the same logic

**Key Consideration**: In PHP, distinguish between:
- Property not set in object (use `isset()` or property_exists + null check)
- Property explicitly set to null (intentional null assignment)
- For arrays: key doesn't exist vs key exists with null value

**What to avoid and WHY**:
- Don't break existing passing tests (backward compatibility matters for library users)
- Don't add complex conditional configuration if a sensible default works (KISS principle)
- Don't ignore nested mappings (incomplete objects can be nested)
</implementation>

<output>
Modify and create files:

1. `./src/Mapper.php` - Update core mapping logic to skip uninitialized properties
2. `./src/Metadata/*` - Update metadata/property handling if needed to track property initialization
3. `./tests/MapperIncompleteSourceTest.php` - New test file covering incomplete source scenarios (or add to existing test file)
4. Update any other files as necessary to support this behavior

Ensure all changes use relative paths from project root.
</output>

<verification>
Before declaring the task complete:

1. Run existing tests to confirm nothing broke:
   - ! php tests/Runner.php (or appropriate test command)

2. Verify incomplete source mapping works:
   - Create a test fixture with an incomplete source object
   - Map it to a target instance
   - Confirm only initialized properties were mapped
   - Confirm uninitialized target properties kept their original values

3. Test edge cases:
   - Source with null value (should NOT map)
   - Source with undefined key in array (should NOT map)
   - Nested incomplete objects (should apply recursively)
   - Mixed: some properties initialized, some not
</verification>

<success_criteria>
- Mapper correctly identifies and skips undefined/null source properties
- Target object properties remain unchanged when source doesn't provide a value
- All existing tests pass (backward compatibility maintained)
- New tests cover incomplete source scenarios and pass
- Code is clean, well-commented, and follows project conventions
- Nested and collection mappings respect the same behavior
</success_criteria>
