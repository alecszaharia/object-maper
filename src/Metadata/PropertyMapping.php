<?php

namespace Alecszaharia\Simmap\Metadata;

/**
 * Represents a bidirectional mapping between two properties.
 *
 * This class stores the property paths for both directions of the mapping,
 * enabling symmetrical object mapping.
 */
class PropertyMapping
{
    public function __construct(
        public readonly string $sourceProperty,
        public readonly string $targetProperty
    ) {
    }

    /**
     * Creates a reversed version of this mapping (swaps source and target).
     */
    public function reverse(): self
    {
        return new self($this->targetProperty, $this->sourceProperty);
    }
}
