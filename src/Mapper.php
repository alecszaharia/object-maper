<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap;

use Alecszaharia\Simmap\Exception\MappingException;
use Alecszaharia\Simmap\Metadata\MappingMetadata;
use Alecszaharia\Simmap\Metadata\MetadataReader;
use Alecszaharia\Simmap\Metadata\PropertyMapping;
use ReflectionClass;
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface as PropertyAccessException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Production-ready bidirectional object mapper using PHP attributes for configuration.
 *
 * Maps objects between classes annotated with #[Mappable] attributes. Supports:
 * - Bidirectional mapping with shared metadata cache
 * - Custom property name mapping via #[MapTo]
 * - Nested property paths using dot notation
 * - Property exclusion via #[IgnoreMap]
 * - Automatic target class instantiation
 *
 * @example
 * $mapper = new Mapper();
 *
 * // Map to existing instance
 * $entity = new User();
 * $mapper->map($dto, $entity);
 *
 * // Map to new instance (auto-instantiated)
 * $entity = $mapper->map($dto, User::class);
 */
final class Mapper implements MapperInterface
{
    /**
     * @var array<string, MappingMetadata> Metadata cache indexed by class pair key
     */
    private array $metadataCache = [];

    /**
     * @var array<string, bool> Tracks cache access order for LRU eviction
     */
    private array $cacheAccessOrder = [];

    /**
     * @var int Maximum number of metadata entries to cache
     */
    private int $maxCacheSize;

    /**
     * @var array<string, bool> Stack of class names currently being mapped (for circular reference detection)
     */
    private array $mappingStack = [];

    private readonly PropertyAccessorInterface $propertyAccessor;
    private readonly MetadataReader $metadataReader;

    public function __construct(
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?MetadataReader $metadataReader = null,
        int $cacheSize = 1000
    ) {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->metadataReader = $metadataReader ?? new MetadataReader();
        $this->maxCacheSize = max(10, $cacheSize);
    }

    /**
     * Map properties from source object to target object.
     *
     * @param object $source Source object to map from
     * @param object|class-string|null $target Target object or class name to map to
     * @return object The populated target object
     * @throws MappingException If mapping fails
     */
    public function map(object $source, object|string|null $target = null): object
    {
        if ($target === null) {
            throw MappingException::targetRequired();
        }

        $sourceClass = get_class($source);

        // Resolve target: instantiate if class name provided
        if (is_string($target)) {
            $targetClass = $target;
            $target = $this->instantiateTarget($targetClass);
        } else {
            $targetClass = get_class($target);
        }

        // Get or build metadata
        $metadata = $this->getMetadata($sourceClass, $targetClass);

        // Validate mapping is allowed
        if (!$metadata->isValidMapping) {
            throw MappingException::nonReciprocal($sourceClass, $targetClass);
        }

        // Execute property mapping
        $propertyMappings = $metadata->getMappingsForDirection($sourceClass, $targetClass);
        $this->executeMapping($source, $target, $propertyMappings, $sourceClass, $targetClass);

        return $target;
    }

    /**
     * Get or build metadata for a class pair.
     *
     * Metadata is cached per unique class pair, not per direction.
     * Uses LRU (Least Recently Used) eviction when cache reaches max size.
     *
     * @param class-string $sourceClass
     * @param class-string $targetClass
     */
    private function getMetadata(string $sourceClass, string $targetClass): MappingMetadata
    {
        $cacheKey = $this->buildCacheKey($sourceClass, $targetClass);

        if (isset($this->metadataCache[$cacheKey])) {
            // Update access order (move to end - most recently used)
            unset($this->cacheAccessOrder[$cacheKey]);
            $this->cacheAccessOrder[$cacheKey] = true;
            return $this->metadataCache[$cacheKey];
        }

        try {
            $metadata = $this->metadataReader->buildMetadata(
                $sourceClass,
                $targetClass
            );
        } catch (\ReflectionException $e) {
            throw new MappingException(
                sprintf('Failed to build metadata: %s', $e->getMessage()),
                0,
                $e
            );
        }

        // Check cache size and evict LRU entry if necessary
        if (count($this->metadataCache) >= $this->maxCacheSize) {
            // Get the least recently used key (first in access order)
            $lruKey = array_key_first($this->cacheAccessOrder);
            unset($this->metadataCache[$lruKey]);
            unset($this->cacheAccessOrder[$lruKey]);
        }

        // Add new entry to cache
        $this->metadataCache[$cacheKey] = $metadata;
        $this->cacheAccessOrder[$cacheKey] = true;

        return $metadata;
    }

    /**
     * Build a cache key for a class pair.
     *
     * The key is order-independent so that mapping A->B and B->A use the same cache entry.
     *
     * @param class-string $classA
     * @param class-string $classB
     */
    private function buildCacheKey(string $classA, string $classB): string
    {
        // Simple string comparison is more efficient than sorting for 2 items
        if ($classA <= $classB) {
            return $classA . '<->' . $classB;
        }
        return $classB . '<->' . $classA;
    }

    /**
     * Instantiate target class with reflection.
     *
     * @param class-string $className
     * @throws MappingException If instantiation fails
     */
    private function instantiateTarget(string $className): object
    {
        try {
            $reflection = new ReflectionClass($className);
            return $reflection->newInstanceWithoutConstructor();
        } catch (\ReflectionException $e) {
            throw MappingException::instantiationFailed($className, $e->getMessage());
        }
    }

    /**
     * Execute property mappings using Symfony PropertyAccessor.
     *
     * @param array<PropertyMapping> $propertyMappings
     * @param class-string $sourceClass
     * @param class-string $targetClass
     * @throws MappingException If property access fails
     */
    private function executeMapping(
        object $source,
        object $target,
        array $propertyMappings,
        string $sourceClass,
        string $targetClass
    ): void {
        foreach ($propertyMappings as $mapping) {
            try {
                // Read value from source (supports nested paths, private properties, getters)
                if(!$this->propertyAccessor->isReadable($source, $mapping->sourceProperty)) {
                    continue;
                }

                $value = $this->propertyAccessor->getValue($source, $mapping->sourceProperty);

                // Skip mapping if source property is null/undefined
                // This allows partial object updates without overwriting existing target properties
                if ($value === null) {
                    continue;
                }

                // Handle array mapping if this is an array property
                if ($mapping->isArray && $value !== null) {
                    $value = $this->mapArray(
                        $value,
                        $mapping,
                        $sourceClass,
                        $targetClass
                    );
                }

                // For nested paths, ensure intermediate objects exist
                $this->ensureNestedPathExists($target, $mapping->targetProperty);

                if(!$this->propertyAccessor->isWritable($target, $mapping->targetProperty)) {
                    continue;
                }

                // Write value to target (supports nested paths, private properties, setters)
                $this->propertyAccessor->setValue($target, $mapping->targetProperty, $value);
            } catch (PropertyAccessException $e) {
                throw MappingException::propertyAccessFailed(
                    $sourceClass,
                    $targetClass,
                    $mapping->sourceProperty . ' -> ' . $mapping->targetProperty,
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * Ensure all intermediate objects in a nested property path exist.
     *
     * For a path like "profile.bio", this ensures "profile" is initialized.
     *
     * @param object $object The object to check
     * @param string $propertyPath The property path (may include dots for nesting)
     */
    private function ensureNestedPathExists(object $object, string $propertyPath): void
    {
        // Check if this is a nested path
        if (!str_contains($propertyPath, '.')) {
            return; // Simple property, no nested path
        }

        // Split the path into parts
        $parts = explode('.', $propertyPath);
        // Remove the last part (the actual property we're setting)
        array_pop($parts);

        // Traverse the path and initialize null objects
        $current = $object;
        $currentPath = [];

        foreach ($parts as $part) {
            $currentPath[] = $part;
            $pathString = implode('.', $currentPath);

            try {
                $value = $this->propertyAccessor->getValue($current, $part);

                if ($value === null) {
                    // Get the property type and instantiate it
                    $newObject = $this->instantiateNestedObject($current, $part);
                    $this->propertyAccessor->setValue($current, $part, $newObject);
                    $current = $newObject;
                } else {
                    $current = $value;
                }
            } catch (PropertyAccessException $e) {
                // If we can't access the property, let it fail naturally in setValue
                return;
            }
        }
    }

    /**
     * Instantiate a nested object based on property type hint.
     *
     * @param object $object The parent object
     * @param string $propertyName The property name
     * @return object The instantiated object
     * @throws MappingException If unable to determine or instantiate the type
     */
    private function instantiateNestedObject(object $object, string $propertyName): object
    {
        try {
            $reflection = new ReflectionClass($object);
            $property = $reflection->getProperty($propertyName);
            $type = $property->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                $nestedReflection = new ReflectionClass($className);
                return $nestedReflection->newInstanceWithoutConstructor();
            }

            throw new MappingException(
                sprintf(
                    'Cannot auto-initialize property "%s" in class "%s": no concrete type hint found',
                    $propertyName,
                    get_class($object)
                )
            );
        } catch (\ReflectionException $e) {
            throw new MappingException(
                sprintf(
                    'Failed to auto-initialize nested property "%s" in class "%s": %s',
                    $propertyName,
                    get_class($object),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Map an array/collection of objects to target class instances.
     *
     * This method handles bidirectional array mapping by recursively mapping each item
     * in the source collection to an instance of the target class.
     *
     * @param mixed $sourceArray The source array/iterable
     * @param PropertyMapping $mapping The property mapping configuration
     * @param class-string $sourceClass The source object class
     * @param class-string $targetClass The target object class
     * @return array|object The mapped array/collection
     * @throws MappingException If array mapping fails
     */
    private function mapArray(
        mixed $sourceArray,
        PropertyMapping $mapping,
        string $sourceClass,
        string $targetClass
    ): array|object {
        // Determine the target item class for array mapping
        // Use targetItemClass (preferred) or fall back to targetClass for backward compatibility
        $targetItemClass = $mapping->targetItemClass ?? $mapping->targetClass;

        // Validate that targetItemClass is specified for array mapping
        if ($targetItemClass === null) {
            throw MappingException::missingTargetClassForArray(
                $sourceClass,
                $targetClass,
                $mapping->sourceProperty
            );
        }

        // Handle null arrays
        if ($sourceArray === null) {
            return [];
        }

        // Convert to array if it's a traversable but not an array
        $isArrayObject = $sourceArray instanceof \ArrayObject;
        $items = is_array($sourceArray) ? $sourceArray : iterator_to_array($sourceArray);

        // Map each item in the array
        $mappedItems = [];
        $index = 0;

        foreach ($items as $key => $item) {
            // Null items are preserved as null
            if ($item === null) {
                $mappedItems[$key] = null;
                $index++;
                continue;
            }

            // Validate that item is an object
            if (!is_object($item)) {
                throw MappingException::arrayItemNotObject(
                    $sourceClass,
                    $targetClass,
                    $mapping->sourceProperty,
                    $index,
                    get_debug_type($item)
                );
            }

            // Detect circular references
            $itemClass = get_class($item);
            if (isset($this->mappingStack[$itemClass])) {
                throw MappingException::circularReferenceDetected(
                    $itemClass,
                    $mapping->sourceProperty . '[' . $index . ']'
                );
            }

            try {
                // Add to mapping stack for circular reference detection
                $this->mappingStack[$itemClass] = true;

                // Recursively map the item to the target item class
                $mappedItems[$key] = $this->map($item, $targetItemClass);

                // Remove from mapping stack
                unset($this->mappingStack[$itemClass]);
            } catch (MappingException $e) {
                // Remove from mapping stack on error
                unset($this->mappingStack[$itemClass]);

                // Re-throw with array context if not already an array mapping error
                if (!str_contains($e->getMessage(), 'array item at index')) {
                    throw MappingException::arrayMappingFailed(
                        $sourceClass,
                        $targetClass,
                        $mapping->sourceProperty,
                        $index,
                        $e->getMessage()
                    );
                }
                throw $e;
            }

            $index++;
        }

        // Preserve collection type: return ArrayObject if source was ArrayObject
        if ($isArrayObject) {
            return new \ArrayObject($mappedItems);
        }

        return $mappedItems;
    }
}
