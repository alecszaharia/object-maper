<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Metadata;

/**
 * Value object representing a bidirectional property mapping between two classes.
 *
 * This mapping works in both directions - from sourceProperty to targetProperty
 * and vice versa. The names are relative to the initial mapping direction but
 * the metadata is reused for reverse mapping.
 *
 * For array properties, both sourceItemClass and targetItemClass are stored to
 * enable correct bidirectional array mapping.
 */
final class PropertyMapping
{
    public readonly ?string $sourceItemClass;
    public readonly ?string $targetItemClass;

    /**
     * @param string $sourceProperty Property name or path in the source class
     * @param string $targetProperty Property name or path in the target class
     * @param bool $isArray Whether this property contains an array/collection
     * @param class-string|null $targetClass For array properties, the class to map each item to (deprecated, use targetItemClass)
     * @param class-string|null $sourceItemClass For array properties, the class of items in source array
     * @param class-string|null $targetItemClass For array properties, the class of items in target array
     */
    public function __construct(
        public readonly string $sourceProperty,
        public readonly string $targetProperty,
        public readonly bool $isArray = false,
        public readonly ?string $targetClass = null,
        ?string $sourceItemClass = null,
        ?string $targetItemClass = null
    ) {
        // For backward compatibility: if targetClass is set but sourceItemClass/targetItemClass are not,
        // use targetClass as targetItemClass
        if ($this->isArray && $this->targetClass !== null && $targetItemClass === null) {
            $targetItemClass = $this->targetClass;
        }

        $this->sourceItemClass = $sourceItemClass;
        $this->targetItemClass = $targetItemClass;
    }

    /**
     * Returns a reversed version of this mapping (swap source and target).
     *
     * For array properties, this also swaps the sourceItemClass and targetItemClass
     * to ensure correct bidirectional array mapping.
     */
    public function reverse(): self
    {
        return new self(
            $this->targetProperty,
            $this->sourceProperty,
            $this->isArray,
            null, // deprecated targetClass parameter, use sourceItemClass/targetItemClass instead
            $this->targetItemClass, // swap: what was target becomes source
            $this->sourceItemClass  // swap: what was source becomes target
        );
    }
}
