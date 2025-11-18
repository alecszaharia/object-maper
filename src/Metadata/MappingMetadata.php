<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Metadata;

/**
 * Value object containing metadata for a bidirectional class mapping.
 *
 * This metadata is cached per unique class pair (not per direction).
 * For example, mapping between UserDTO and User shares the same metadata
 * regardless of which direction the mapping occurs.
 */
final readonly class MappingMetadata
{
    /**
     * @param class-string $classA First class in the mapping pair
     * @param class-string $classB Second class in the mapping pair
     * @param array<PropertyMapping> $propertyMappings Bidirectional property mappings
     * @param bool $isValidMapping Whether both classes have reciprocal #[Mappable] attributes
     */
    public function __construct(
        public string $classA,
        public string $classB,
        public array $propertyMappings,
        public bool $isValidMapping
    ) {
    }

    /**
     * Get property mappings for a specific direction.
     *
     * @param class-string $sourceClass
     * @param class-string $targetClass
     * @return array<PropertyMapping>
     */
    public function getMappingsForDirection(string $sourceClass, string $targetClass): array
    {
        // If the direction matches the stored order, return as-is
        if ($this->classA === $sourceClass && $this->classB === $targetClass) {
            return $this->propertyMappings;
        }

        // Otherwise, reverse all mappings for the opposite direction
        return array_map(
            fn(PropertyMapping $mapping) => $mapping->reverse(),
            $this->propertyMappings
        );
    }
}
