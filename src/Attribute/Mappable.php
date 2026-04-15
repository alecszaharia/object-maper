<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Attribute;

use Attribute;

/**
 * Marks a class as mappable to another class.
 *
 * This attribute is repeatable, allowing a class to be mapped to multiple target classes.
 * For bidirectional mapping to work, both classes must have #[Mappable] attributes
 * pointing to each other.
 *
 * @example
 * #[Mappable(targetClass: UserEntity::class)]
 * class UserDTO { }
 *
 * #[Mappable(targetClass: UserDTO::class)]
 * class UserEntity { }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Mappable
{
    /**
     * @param class-string $targetClass The fully qualified class name this class can map to
     */
    public function __construct(
        public readonly string $targetClass
    ) {
    }
}
