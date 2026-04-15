<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Attribute;

use Attribute;

/**
 * Maps a property to a different property name in the target class.
 *
 * Supports nested property paths using dot notation for accessing/setting
 * nested object properties via Symfony PropertyAccessor.
 *
 * For array/collection properties, use targetClass to specify the class to map each item to.
 *
 * @example
 * // Simple property name mapping
 * #[MapTo(targetProperty: 'fullName')]
 * private string $name;
 *
 * @example
 * // Nested property path mapping
 * #[MapTo(targetProperty: 'address.street')]
 * private string $streetName;
 *
 * @example
 * // Array mapping with target class
 * #[MapTo(targetProperty: 'users', targetClass: User::class)]
 * private array $userDTOs;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class MapTo
{
    /**
     * @param string $targetProperty The target property name or path (supports dot notation)
     * @param class-string|null $targetClass The target class for array items (optional, for array mapping)
     */
    public function __construct(
        public readonly string $targetProperty,
        public readonly ?string $targetClass = null
    ) {
    }
}
