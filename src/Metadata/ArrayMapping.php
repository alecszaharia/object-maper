<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Metadata;

/**
 * Represents an array property mapping with element type conversion.
 *
 * This class stores metadata for mapping array properties where each element
 * needs to be converted from one type to another during the mapping process.
 */
class ArrayMapping
{
    /**
     * @param string $propertyName The name of the array property
     * @param class-string $targetElementClass The class to map each array element to
     */
    public function __construct(
        public readonly string $propertyName,
        public readonly string $targetElementClass
    ) {
    }
}
