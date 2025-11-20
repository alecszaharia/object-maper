<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Metadata;

use Alecszaharia\Simmap\Attribute\IgnoreMap;
use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;
use ReflectionClass;
use ReflectionProperty;

/**
 * Reads PHP attributes from classes and builds mapping metadata.
 *
 * This class uses reflection to extract #[Mappable], #[MapTo], and #[IgnoreMap]
 * attributes and constructs MappingMetadata objects that are cached for performance.
 */
final class MetadataReader
{
    /**
     * @var array<string, bool> Cache for subclass checks to avoid repeated is_subclass_of() calls
     */
    private array $subclassCache = [];

    /**
     * Built-in iterable classes for quick lookup
     */
    private const ITERABLE_CLASSES = [
        'ArrayObject' => true,
        'ArrayIterator' => true,
        'Iterator' => true,
        'Traversable' => true,
        'IteratorAggregate' => true,
    ];
    /**
     * Build mapping metadata for a class pair.
     *
     * Creates a bidirectional metadata object that can be used for mapping
     * in either direction between the two classes.
     *
     * @param class-string $sourceClass
     * @param class-string $targetClass
     * @throws \ReflectionException
     */
    public function buildMetadata(string $sourceClass, string $targetClass): MappingMetadata
    {
        $sourceReflection = new ReflectionClass($sourceClass);
        $targetReflection = new ReflectionClass($targetClass);

        // Validate reciprocal mapping
        $isValidMapping = $this->hasReciprocalMapping($sourceReflection, $targetClass)
            && $this->hasReciprocalMapping($targetReflection, $sourceClass);

        // Build property mappings
        $propertyMappings = $this->buildPropertyMappings($sourceReflection, $targetReflection);

        return new MappingMetadata(
            $sourceClass,
            $targetClass,
            $propertyMappings,
            $isValidMapping
        );
    }

    /**
     * Check if a class has #[Mappable] attribute pointing to target class.
     *
     * @param ReflectionClass<object> $reflection
     * @param class-string $targetClass
     */
    private function hasReciprocalMapping(ReflectionClass $reflection, string $targetClass): bool
    {
        $mappableAttributes = $reflection->getAttributes(Mappable::class);

        foreach ($mappableAttributes as $attribute) {
            /** @var Mappable $mappable */
            $mappable = $attribute->newInstance();
            if ($mappable->targetClass === $targetClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build property mappings between two classes.
     *
     * Collects mappings from BOTH source and target classes to enable true bidirectional mapping.
     * This ensures that properties with #[MapTo] attributes on either side work in both directions.
     *
     * @param ReflectionClass<object> $sourceReflection
     * @param ReflectionClass<object> $targetReflection
     * @return array<PropertyMapping>
     */
    private function buildPropertyMappings(
        ReflectionClass $sourceReflection,
        ReflectionClass $targetReflection
    ): array {
        $mappings = [];
        $mappingKeys = []; // Track unique mappings to avoid duplicates (sourceProperty => targetProperty)

        // Step 1: Collect mappings from source → target (existing behavior)
        $this->collectMappingsFromClass(
            $sourceReflection,
            $targetReflection,
            $mappings,
            $mappingKeys,
            false // not reversed
        );

        // Step 2: Collect mappings from target → source (new behavior for bidirectional support)
        // We reverse the direction to find properties on target that should map back to source
        $this->collectMappingsFromClass(
            $targetReflection,
            $sourceReflection,
            $mappings,
            $mappingKeys,
            true // reversed - swap source and target in the resulting mappings
        );

        return $mappings;
    }

    /**
     * Collect property mappings from one class to another.
     *
     * @param ReflectionClass<object> $fromReflection Class to read properties from
     * @param ReflectionClass<object> $toReflection Class to map properties to
     * @param array<PropertyMapping> $mappings Output array to append mappings to
     * @param array<string, bool> $mappingKeys Track unique mappings to avoid duplicates
     * @param bool $reversed If true, swap source and target in resulting PropertyMapping
     */
    private function collectMappingsFromClass(
        ReflectionClass $fromReflection,
        ReflectionClass $toReflection,
        array &$mappings,
        array &$mappingKeys,
        bool $reversed
    ): void {
        // Convert property names to hash map for O(1) lookups instead of O(n) linear search
        $toPropertiesNames = $this->getPropertyNames($toReflection);
        $toPropertiesMap = array_flip($toPropertiesNames);

        foreach ($fromReflection->getProperties() as $fromProperty) {
            // Skip properties marked with #[IgnoreMap] in source
            if ($this->isIgnored($fromProperty)) {
                continue;
            }

            $fromName = $fromProperty->getName();

            // Check for #[MapTo] attribute
            $mapToAttribute = $this->getMapToAttribute($fromProperty);

            if ($mapToAttribute !== null) {
                // Custom mapping via #[MapTo]
                $toName = $mapToAttribute->targetProperty;

                // Validate that target path exists (at least the root property)
                if (!$this->targetPathExists($toReflection, $toName)) {
                    continue;
                }
            } elseif (isset($toPropertiesMap[$fromName])) {
                // Default: map to same property name (O(1) hash lookup)
                $toName = $fromName;
            } else {
                // No matching property and no #[MapTo], skip
                continue;
            }

            // Skip if target property has #[IgnoreMap]
            if ($this->isTargetPropertyIgnored($toReflection, $toName)) {
                continue;
            }

            // Determine actual source and target property names based on direction
            $sourceProperty = $reversed ? $toName : $fromName;
            $targetProperty = $reversed ? $fromName : $toName;

            // Create a unique key to detect duplicates
            // For simple properties, key is "sourceProp => targetProp"
            // For nested properties like "profile.bio => biography", we want to treat
            // "profile.bio => biography" and "biography => profile.bio" as the same mapping
            $mappingKey = $sourceProperty . ' => ' . $targetProperty;

            // Skip if we already have this exact mapping
            if (isset($mappingKeys[$mappingKey])) {
                continue;
            }

            // Detect if this is an array property and extract source/target item classes
            $isArray = $this->isArrayProperty($fromProperty);
            $targetClass = null;
            $sourceItemClass = null;
            $targetItemClass = null;

            if ($isArray) {
                // For array properties, we need to determine both sourceItemClass and targetItemClass
                // to support bidirectional array mapping

                if ($reversed) {
                    // When reversed, fromProperty is actually on the target class
                    // and toProperty is on the source class

                    // targetItemClass comes from the "from" property (which is actually target)
                    if ($mapToAttribute !== null && $mapToAttribute->targetClass !== null) {
                        $targetItemClass = $mapToAttribute->targetClass;
                        $targetClass = $targetItemClass; // backward compatibility
                    }

                    // sourceItemClass comes from the "to" property (which is actually source)
                    $toProperty = $this->findPropertyByPath($toReflection, $toName);
                    if ($toProperty !== null) {
                        $toMapToAttribute = $this->getMapToAttribute($toProperty);
                        if ($toMapToAttribute !== null && $toMapToAttribute->targetClass !== null) {
                            $sourceItemClass = $toMapToAttribute->targetClass;
                        }
                    }
                } else {
                    // Normal direction: fromProperty is source, toProperty is target

                    // Get targetItemClass from source property's #[MapTo] attribute
                    if ($mapToAttribute !== null && $mapToAttribute->targetClass !== null) {
                        $targetItemClass = $mapToAttribute->targetClass;
                        $targetClass = $targetItemClass; // backward compatibility
                    }

                    // Get sourceItemClass from target property's #[MapTo] attribute (for reverse mapping)
                    $toProperty = $this->findPropertyByPath($toReflection, $toName);
                    if ($toProperty !== null) {
                        $toMapToAttribute = $this->getMapToAttribute($toProperty);
                        if ($toMapToAttribute !== null && $toMapToAttribute->targetClass !== null) {
                            $sourceItemClass = $toMapToAttribute->targetClass;
                        }
                    }
                }
            }

            $mappings[] = new PropertyMapping(
                $sourceProperty,
                $targetProperty,
                $isArray,
                $targetClass,
                $sourceItemClass,
                $targetItemClass
            );

            // Mark this mapping as processed
            $mappingKeys[$mappingKey] = true;
        }
    }

    /**
     * Check if a property path exists in the target class.
     *
     * For nested paths like "profile.bio", checks if "profile" property exists.
     *
     * @param ReflectionClass<object> $reflection
     * @param string $propertyPath
     */
    private function targetPathExists(ReflectionClass $reflection, string $propertyPath): bool
    {
        // Extract root property from path
        $rootProperty = str_contains($propertyPath, '.')
            ? explode('.', $propertyPath)[0]
            : $propertyPath;

        return $reflection->hasProperty($rootProperty);
    }

    /**
     * Check if target property (by name) has #[IgnoreMap] attribute.
     *
     * For nested paths, checks only the final property.
     *
     * @param ReflectionClass<object> $reflection
     * @param string $propertyPath
     */
    private function isTargetPropertyIgnored(ReflectionClass $reflection, string $propertyPath): bool
    {
        // For nested paths like "profile.bio", we can't easily check the nested property's attributes
        // So we only check direct properties
        if (str_contains($propertyPath, '.')) {
            return false;
        }

        if (!$reflection->hasProperty($propertyPath)) {
            return false;
        }

        $property = $reflection->getProperty($propertyPath);
        return $this->isIgnored($property);
    }

    /**
     * Get all property names from a class (including inherited).
     *
     * @param ReflectionClass<object> $reflection
     * @return array<string>
     */
    private function getPropertyNames(ReflectionClass $reflection): array
    {
        return array_map(
            fn(ReflectionProperty $prop) => $prop->getName(),
            $reflection->getProperties()
        );
    }

    /**
     * Check if property has #[IgnoreMap] attribute.
     */
    private function isIgnored(ReflectionProperty $property): bool
    {
        return count($property->getAttributes(IgnoreMap::class)) > 0;
    }

    /**
     * Get #[MapTo] attribute from property if present.
     */
    private function getMapToAttribute(ReflectionProperty $property): ?MapTo
    {
        $attributes = $property->getAttributes(MapTo::class);

        if (count($attributes) === 0) {
            return null;
        }

        /** @var MapTo */
        return $attributes[0]->newInstance();
    }

    /**
     * Detect if a property is an array or iterable type.
     *
     * Checks the property type hint to determine if it's an array, iterable,
     * or a traversable collection type (ArrayObject, Iterator, etc.).
     */
    private function isArrayProperty(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        if ($type === null) {
            // No type hint - we can't determine if it's an array
            return false;
        }

        // Handle union types (e.g., array|null)
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($this->isArrayType($unionType)) {
                    return true;
                }
            }
            return false;
        }

        // Handle single type
        return $this->isArrayType($type);
    }

    /**
     * Check if a reflection type is an array or iterable type.
     *
     * Uses caching to avoid repeated is_subclass_of() calls which are expensive.
     */
    private function isArrayType(\ReflectionType $type): bool
    {
        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        $typeName = $type->getName();

        // Check for built-in array/iterable types
        if ($typeName === 'array' || $typeName === 'iterable') {
            return true;
        }

        // Check hash map first for direct match (O(1))
        if (isset(self::ITERABLE_CLASSES[$typeName])) {
            return true;
        }

        // Check cache for subclass results (avoid expensive is_subclass_of)
        if (isset($this->subclassCache[$typeName])) {
            return $this->subclassCache[$typeName];
        }

        // Check subclass relationships (expensive - only done once per type)
        $isIterable = false;
        foreach (self::ITERABLE_CLASSES as $iterableClass => $_) {
            if (is_subclass_of($typeName, $iterableClass)) {
                $isIterable = true;
                break;
            }
        }

        // Cache the result
        $this->subclassCache[$typeName] = $isIterable;

        return $isIterable;
    }

    /**
     * Find a property by path (supports nested paths like "profile.bio").
     *
     * For nested paths, returns the root property.
     * Returns null if property doesn't exist.
     *
     * @param ReflectionClass<object> $reflection
     * @param string $propertyPath
     * @return ReflectionProperty|null
     */
    private function findPropertyByPath(ReflectionClass $reflection, string $propertyPath): ?ReflectionProperty
    {
        // Extract root property from path
        $rootProperty = str_contains($propertyPath, '.')
            ? explode('.', $propertyPath)[0]
            : $propertyPath;

        if (!$reflection->hasProperty($rootProperty)) {
            return null;
        }

        return $reflection->getProperty($rootProperty);
    }
}
