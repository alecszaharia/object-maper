<?php

namespace Alecszaharia\Simmap\Metadata;

use Alecszaharia\Simmap\Attribute\Ignore;
use Alecszaharia\Simmap\Attribute\MapTo;
use ReflectionClass;
use ReflectionProperty;

/**
 * Extracts mapping metadata from class attributes using Reflection.
 *
 * This class scans a class's properties for mapping attributes and builds
 * a MappingMetadata object containing all mapping information.
 */
class MetadataReader
{
    /**
     * @var array<string, MappingMetadata>
     */
    private array $cache = [];

    /**
     * Reads mapping metadata from a class.
     *
     * @param string|object $class Class name or instance
     */
    public function getMetadata(string|object $class): MappingMetadata
    {
        $className = is_string($class) ? $class : get_class($class);

        if (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

        $reflection = new ReflectionClass($className);
        $metadata = new MappingMetadata($className, [], [], $reflection);

        // Get all properties including inherited ones
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) as $property) {
            $this->processProperty($property, $metadata);
        }

        $this->cache[$className] = $metadata;

        return $metadata;
    }

    /**
     * Processes a single property and extracts its mapping metadata.
     */
    private function processProperty(ReflectionProperty $property, MappingMetadata $metadata): void
    {
        $propertyName = $property->getName();

        // Check for Ignore attribute
        $ignoreAttributes = $property->getAttributes(Ignore::class);
        if (!empty($ignoreAttributes)) {
            $metadata->addIgnoredProperty($propertyName);
            return;
        }

        // Check for MapTo attribute
        $mapToAttributes = $property->getAttributes(MapTo::class);
        if (!empty($mapToAttributes)) {
            /** @var MapTo $mapTo */
            $mapTo = $mapToAttributes[0]->newInstance();
            $metadata->addMapping(new PropertyMapping($propertyName, $mapTo->targetProperty));
        }
    }

    /**
     * Clears the metadata cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
