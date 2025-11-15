<?php

namespace Alecszaharia\Simmap\Attribute;

use Attribute;

/**
 * Defines a mapping between properties of two objects.
 *
 * This attribute supports symmetrical mapping - it can be read from either direction.
 * The targetProperty parameter can use PropertyAccess notation for nested properties.
 *
 * Examples:
 *   #[MapTo('name')]              - Maps to simple property
 *   #[MapTo('user.name')]         - Maps to nested property
 *   #[MapTo('address.city.name')] - Maps to deeply nested property
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MapTo
{
    public function __construct(
        public readonly string $targetProperty
    ) {
    }
}
