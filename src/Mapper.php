<?php

namespace Alecszaharia\Simmap;

use Alecszaharia\Simmap\Exception\MappingException;
use Alecszaharia\Simmap\Metadata\MappingMetadata;
use Alecszaharia\Simmap\Metadata\MetadataReader;
use ReflectionClass;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class Mapper implements MapperInterface
{
    private PropertyAccessorInterface $propertyAccessor;
    private MetadataReader $metadataReader;

    public function __construct(
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?MetadataReader $metadataReader = null
    ) {
        $this->propertyAccessor = $propertyAccessor ?? new PropertyAccessor();
        $this->metadataReader = $metadataReader ?? new MetadataReader();
    }

    /**
     * Maps properties from source object to target object.
     *
     * @param object $source Source object to map from
     * @param object|string|null $target Target object, class name, or null to return source
     * @return object The mapped target object
     * @throws MappingException
     */
    public function map(object $source, object|string|null $target = null): object
    {
        // If no target specified, return source (no mapping needed)
        if ($target === null) {
            return $source;
        }

        // Create target instance if a class name was provided
        $targetObject = $this->resolveTargetObject($target);

        // Get metadata for both source and target classes
        $sourceMetadata = $this->metadataReader->getMetadata($source);
        $targetMetadata = $this->metadataReader->getMetadata($targetObject);

        // Validate both classes are mappable
        if (!$sourceMetadata->isMappable) {
            throw MappingException::notMappable(get_class($source), 'source');
        }

        if (!$targetMetadata->isMappable) {
            throw MappingException::notMappable(get_class($targetObject), 'target');
        }

        // Get all properties from source object using cached reflection
        $sourceProperties = $sourceMetadata->reflection->getProperties();

        foreach ($sourceProperties as $property) {
            $propertyName = $property->getName();

            // Skip if property is ignored in source metadata
            if ($sourceMetadata->isPropertyIgnored($propertyName)) {
                continue;
            }

            // Determine target property path
            $targetPropertyPath = $this->resolveTargetPropertyPath(
                $propertyName,
                $sourceMetadata,
                $targetMetadata
            );

            if ($targetPropertyPath === null) {
                continue; // No mapping found and property doesn't exist on target
            }

            // Read value from source using PropertyAccessor
            try {
                $value = $this->propertyAccessor->getValue($source, $propertyName);
            } catch (NoSuchPropertyException | AccessException | UninitializedPropertyException $e) {
                // Skip properties that can't be read (non-existent, inaccessible, or uninitialized)
                continue;
            }

            // Check if this is an array mapping - if so, map each element
            $arrayMapping = $sourceMetadata->getArrayMapping($propertyName);
            if ($arrayMapping !== null && is_array($value)) {
                $value = $this->mapArray($value, $arrayMapping->targetElementClass);
            }

            // Write value to target using PropertyAccessor
            try {
                if ($this->propertyAccessor->isWritable($targetObject, $targetPropertyPath)) {
                    $this->propertyAccessor->setValue($targetObject, $targetPropertyPath, $value);
                }
            } catch (NoSuchPropertyException | AccessException | InvalidArgumentException $e) {
                throw MappingException::propertyAccessError(
                    get_class($targetObject),
                    $targetPropertyPath,
                    $e->getMessage()
                );
            }
        }

        return $targetObject;
    }

    /**
     * Resolves the target object from a class name or returns the existing object.
     */
    private function resolveTargetObject(object|string $target): object
    {
        if (is_object($target)) {
            return $target;
        }

        if (!is_string($target)) {
            throw MappingException::invalidTargetType($target);
        }

        // Try to create an instance of the target class
        try {
            $reflection = new ReflectionClass($target);

            if (!$reflection->isInstantiable()) {
                throw MappingException::cannotCreateInstance(
                    $target,
                    'Class is not instantiable (abstract or interface)'
                );
            }

            return $reflection->newInstanceWithoutConstructor();
        } catch (\ReflectionException $e) {
            throw MappingException::cannotCreateInstance($target, $e->getMessage());
        }
    }

    /**
     * Resolves the target property path for a source property.
     *
     * This method implements the symmetrical mapping logic:
     * 1. Check if source has explicit MapTo attribute
     * 2. Check if target has MapTo attribute pointing back to this source property
     * 3. Auto-map if properties have same name and target property exists
     */
    private function resolveTargetPropertyPath(
        string $sourceProperty,
        MappingMetadata $sourceMetadata,
        MappingMetadata $targetMetadata
    ): ?string {
        // Check explicit mapping in source metadata
        $explicitMapping = $sourceMetadata->findTargetProperty($sourceProperty);
        if ($explicitMapping !== null) {
            return $explicitMapping;
        }

        // Check for reverse mapping in target metadata (symmetrical mapping) using O(1) index lookup
        $reverseMapping = $targetMetadata->findSourceProperty($sourceProperty);
        if ($reverseMapping !== null) {
            return $reverseMapping;
        }

        // Auto-map if property exists on target and is not ignored
        if (!$targetMetadata->isPropertyIgnored($sourceProperty)) {
            // Check if property exists on target class using cached reflection
            if ($targetMetadata->reflection->hasProperty($sourceProperty)) {
                return $sourceProperty;
            }
        }

        return null;
    }

    /**
     * Maps an array of objects to another array, converting each element.
     *
     * This method recursively maps each element in the source array to the target class,
     * preserving array keys for both indexed and associative arrays.
     *
     * @param array $sourceArray The array of source objects
     * @param string $targetClass The target class to map each element to
     * @return array The mapped array with same keys
     */
    private function mapArray(array $sourceArray, string $targetClass): array
    {
        $mappedArray = [];

        foreach ($sourceArray as $key => $element) {
            // Only map if element is an object
            if (is_object($element)) {
                $mappedArray[$key] = $this->map($element, $targetClass);
            } else {
                // Preserve non-object values as-is (scalars, arrays, etc.)
                $mappedArray[$key] = $element;
            }
        }

        return $mappedArray;
    }
}