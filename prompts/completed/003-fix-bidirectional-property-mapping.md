<objective>
Fix the bidirectional mapping issue in MetadataReader where properties are not being mapped in both directions.

Currently, the mapper only collects property mappings from the source class when building metadata. This means properties with #[MapTo] attributes on the target class are ignored when mapping in the reverse direction. The goal is to ensure ALL mappable properties from BOTH classes are collected during metadata building, regardless of mapping direction, so that bidirectional mapping works correctly for all annotated properties.
</objective>

<context>
Project: Simmap - A symmetrical PHP object mapper library
Tech stack: PHP 8.2+, Symfony PropertyAccess, PHPUnit
Problem location: src/Metadata/MetadataReader.php:71 (buildPropertyMappings method)

The mapper is designed to be bidirectional - objects should map both directions (e.g., User ↔ UserDTO). However, the current implementation in `MetadataReader::buildPropertyMappings()` only iterates through source class properties, which means:

1. When mapping User → UserDTO, it collects mappings based on User's properties
2. When mapping UserDTO → User, it collects mappings based on UserDTO's properties
3. If a property has a #[MapTo] attribute only on one side, it won't work in the reverse direction

Example scenario that's failing:
- UserDTO has `biography` property with `#[MapTo('profile.bio', targetClass: Profile::class)]`
- User has `profile` property (contains Profile object with `bio` property)
- UserDTO → User works (biography maps to profile.bio)
- User → UserDTO FAILS (profile.bio should map back to biography, but doesn't)

Why this happens: When mapping User → UserDTO, we iterate User's properties, see `profile`, but don't know it should map to `biography` because that #[MapTo] attribute is on UserDTO's side.

Review the failing test at tests/Unit/MapperTest.php:330-348 (testReverseNestedArrayMapping) to understand the expected behavior.
</context>

<requirements>
1. Modify `MetadataReader::buildPropertyMappings()` to collect mappings from BOTH source and target classes
2. Ensure bidirectional mapping metadata is captured regardless of which direction the mapping will occur
3. Properties with #[MapTo] attributes on either side should be included in the mapping metadata
4. Avoid duplicate mappings - if a property maps the same way from both sides, include it only once
5. Preserve all existing functionality (nested paths, #[IgnoreMap], array mappings, etc.)
6. Maintain the current PropertyMapping structure and data
7. Update the Mapper class if needed to handle the enhanced bidirectional metadata
</requirements>

<implementation>
Read and understand the current implementation:
- src/Metadata/MetadataReader.php (focus on buildPropertyMappings method)
- src/Metadata/PropertyMapping.php (understand the mapping structure)
- src/Mapper.php (understand how PropertyMapping is used during actual mapping)

Key changes needed:
1. In `buildPropertyMappings()`, iterate through BOTH source and target class properties
2. For each property in the source class, check for mappings (as currently done)
3. ADDITIONALLY, for each property in the target class, check if it has #[MapTo] that points back to source properties
4. Create PropertyMapping entries that work in both directions
5. Handle edge cases:
   - Avoid duplicate entries for properties that match by name on both sides
   - Respect #[IgnoreMap] on both sides
   - Handle nested paths (e.g., biography → profile.bio and profile.bio → biography)
   - Ensure array mappings with targetClass work bidirectionally

Implementation approach:
- Collect mappings from source → target (current behavior)
- Collect mappings from target → source (new behavior, reverse the direction)
- Merge the two sets of mappings intelligently to avoid duplicates
- Ensure the PropertyMapping objects contain all necessary information for bidirectional use

Note: The actual mapping direction is determined at map() call time, so the metadata should support both directions.
</implementation>

<output>
Modify the following file:
- `src/Metadata/MetadataReader.php` - Update buildPropertyMappings() method to collect from both classes

If PropertyMapping class needs updates to support bidirectional metadata, modify:
- `src/Metadata/PropertyMapping.php`

If Mapper needs updates to use the enhanced metadata, modify:
- `src/Mapper.php`
</output>

<verification>
After making changes:

1. Run the failing test to verify the fix:
   ```
   make test tests/Unit/MapperTest.php::testReverseNestedArrayMapping
   ```

2. Run the complete test suite to ensure no regressions:
   ```
   make test
   ```

3. Verify that all these scenarios work bidirectionally:
   - Properties with same names (e.g., email)
   - Properties with #[MapTo] custom mapping (e.g., fullName ↔ name)
   - Nested property paths (e.g., biography ↔ profile.bio)
   - Array properties with targetClass
   - Properties with #[IgnoreMap]

4. Confirm the test at line 347 assertion is corrected if needed
</verification>

<success_criteria>
- All tests pass, including testReverseNestedArrayMapping
- Properties with #[MapTo] on either source or target class work in both mapping directions
- Nested paths work bidirectionally (e.g., biography → profile.bio and profile.bio → biography)
- No duplicate mappings are created
- Existing functionality remains intact (all other tests still pass)
- Code is clean, well-documented, and follows existing patterns in the codebase
</success_criteria>
