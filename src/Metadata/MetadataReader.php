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
     * @param ReflectionClass<object> $sourceReflection
     * @param ReflectionClass<object> $targetReflection
     * @return array<PropertyMapping>
     */
    private function buildPropertyMappings(
        ReflectionClass $sourceReflection,
        ReflectionClass $targetReflection
    ): array {
        $mappings = [];
        $targetProperties = $this->getPropertyNames($targetReflection);

        foreach ($sourceReflection->getProperties() as $sourceProperty) {
            // Skip properties marked with #[IgnoreMap] in source
            if ($this->isIgnored($sourceProperty)) {
                continue;
            }

            $sourceName = $sourceProperty->getName();

            // Check for #[MapTo] attribute
            $mapToAttribute = $this->getMapToAttribute($sourceProperty);

            if ($mapToAttribute !== null) {
                // Custom mapping via #[MapTo]
                $targetName = $mapToAttribute->targetProperty;

                // Validate that target path exists (at least the root property)
                if (!$this->targetPathExists($targetReflection, $targetName)) {
                    continue;
                }
            } elseif (in_array($sourceName, $targetProperties, true)) {
                // Default: map to same property name
                $targetName = $sourceName;
            } else {
                // No matching property and no #[MapTo], skip
                continue;
            }

            // Skip if target property has #[IgnoreMap]
            if ($this->isTargetPropertyIgnored($targetReflection, $targetName)) {
                continue;
            }

            // Detect if this is an array property and extract source/target item classes
            $isArray = $this->isArrayProperty($sourceProperty);
            $targetClass = null;
            $sourceItemClass = null;
            $targetItemClass = null;

            if ($isArray) {
                // For array properties, we need to determine both sourceItemClass and targetItemClass
                // to support bidirectional array mapping

                // Get targetItemClass from source property's #[MapTo] attribute
                if ($mapToAttribute !== null && $mapToAttribute->targetClass !== null) {
                    $targetItemClass = $mapToAttribute->targetClass;
                    $targetClass = $targetItemClass; // backward compatibility
                }

                // Get sourceItemClass from target property's #[MapTo] attribute (for reverse mapping)
                $targetProperty = $this->findPropertyByPath($targetReflection, $targetName);
                if ($targetProperty !== null) {
                    $targetMapToAttribute = $this->getMapToAttribute($targetProperty);
                    if ($targetMapToAttribute !== null && $targetMapToAttribute->targetClass !== null) {
                        $sourceItemClass = $targetMapToAttribute->targetClass;
                    }
                }
            }

            $mappings[] = new PropertyMapping(
                $sourceName,
                $targetName,
                $isArray,
                $targetClass,
                $sourceItemClass,
                $targetItemClass
            );
        }

        return $mappings;
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

        // Check for common collection classes
        $iterableClasses = [
            'ArrayObject',
            'ArrayIterator',
            'Iterator',
            'Traversable',
            'IteratorAggregate',
        ];

        foreach ($iterableClasses as $iterableClass) {
            if ($typeName === $iterableClass || is_subclass_of($typeName, $iterableClass)) {
                return true;
            }
        }

        return false;
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
