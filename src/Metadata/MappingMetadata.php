<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Metadata;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Holds all property mapping metadata for a class.
 *
 * This class stores both explicit mappings (defined via attributes)
 * and provides functionality to support symmetrical mapping.
 *
 * Performance characteristics:
 * - findTargetProperty(): O(1) via forward index
 * - findSourceProperty(): O(1) via reverse index
 * - isPropertyIgnored(): O(1) via associative array
 *
 * Duplicate handling:
 * - Duplicate source properties in mappings are rejected (exception thrown)
 * - Duplicate target properties in mappings are rejected (exception thrown)
 * - Duplicate ignored properties are silently skipped
 */
class MappingMetadata
{
    public readonly ReflectionClass $reflection;

    /**
     * @var array<string, string> Forward mapping index: sourceProperty => targetProperty
     */
    private array $forwardMappingIndex = [];

    /**
     * @var array<string, string> Reverse mapping index: targetProperty => sourceProperty
     */
    private array $reverseMappingIndex = [];

    /**
     * @var PropertyMapping[]
     */
    private array $mappings = [];

    /**
     * @var array<string, true> Associative array for O(1) ignored property lookups
     */
    private array $ignoredProperties = [];

    /**
     * @param string $className
     * @param PropertyMapping[] $mappings
     * @param string[] $ignoredProperties
     * @param ReflectionClass|null $reflection
     * @throws ReflectionException if className does not exist
     * @throws InvalidArgumentException if duplicate mappings are provided or invalid types in arrays
     */
    public function __construct(
        public readonly string $className,
        array $mappings = [],
        array $ignoredProperties = [],
        ?ReflectionClass $reflection = null
    ) {
        $this->reflection = $reflection ?? new ReflectionClass($className);

        // Validate and add mappings using addMapping() for consistent duplicate detection
        foreach ($mappings as $mapping) {
            if (!$mapping instanceof PropertyMapping) {
                throw new InvalidArgumentException(
                    sprintf(
                        'All mappings must be instances of PropertyMapping, %s given',
                        get_debug_type($mapping)
                    )
                );
            }
            $this->addMapping($mapping);
        }

        // Validate and add ignored properties using addIgnoredProperty()
        foreach ($ignoredProperties as $property) {
            if (!is_string($property)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'All ignored properties must be strings, %s given',
                        get_debug_type($property)
                    )
                );
            }
            $this->addIgnoredProperty($property);
        }
    }

    /**
     * Adds a property mapping.
     *
     * @throws InvalidArgumentException if source or target property already exists in mappings
     */
    public function addMapping(PropertyMapping $mapping): void
    {
        // Check for duplicate source property
        if (isset($this->forwardMappingIndex[$mapping->sourceProperty])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Duplicate source property "%s" in mapping for class "%s". ' .
                    'Property already maps to "%s".',
                    $mapping->sourceProperty,
                    $this->className,
                    $this->forwardMappingIndex[$mapping->sourceProperty]
                )
            );
        }

        // Check for duplicate target property
        if (isset($this->reverseMappingIndex[$mapping->targetProperty])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Duplicate target property "%s" in mapping for class "%s". ' .
                    'Property "%s" already maps to this target.',
                    $mapping->targetProperty,
                    $this->className,
                    $this->reverseMappingIndex[$mapping->targetProperty]
                )
            );
        }

        $this->mappings[] = $mapping;
        $this->forwardMappingIndex[$mapping->sourceProperty] = $mapping->targetProperty;
        $this->reverseMappingIndex[$mapping->targetProperty] = $mapping->sourceProperty;
    }

    /**
     * Adds a property to the ignore list.
     * Silently skips if property is already ignored.
     */
    public function addIgnoredProperty(string $property): void
    {
        $this->ignoredProperties[$property] = true;
    }

    /**
     * Gets all property mappings.
     *
     * @return PropertyMapping[]
     */
    public function getMappings(): array
    {
        return $this->mappings;
    }

    /**
     * Gets all ignored properties.
     *
     * Note: Returns property names as array values (not keys).
     *
     * @return string[]
     */
    public function getIgnoredProperties(): array
    {
        return array_keys($this->ignoredProperties);
    }

    /**
     * Checks if a property should be ignored.
     *
     * Performance: O(1) via associative array lookup.
     */
    public function isPropertyIgnored(string $property): bool
    {
        return isset($this->ignoredProperties[$property]);
    }

    /**
     * Finds the target property path for a given source property using forward index (O(1)).
     * Returns null if no explicit mapping exists.
     */
    public function findTargetProperty(string $sourceProperty): ?string
    {
        return $this->forwardMappingIndex[$sourceProperty] ?? null;
    }

    /**
     * Finds the source property for a given target property using reverse index (O(1)).
     * Returns null if no reverse mapping exists.
     */
    public function findSourceProperty(string $targetProperty): ?string
    {
        return $this->reverseMappingIndex[$targetProperty] ?? null;
    }
}
