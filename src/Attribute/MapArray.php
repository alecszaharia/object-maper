<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Attribute;

use Attribute;

/**
 * Defines automatic mapping for array properties with element type conversion.
 *
 * This attribute enables automatic mapping of array elements from one type to another.
 * Each element in the source array will be mapped to the specified target class.
 *
 * Can be combined with #[MapTo] to also change the property name:
 *   #[MapArray(OrderItem::class)]
 *   #[MapTo('orderItems')]
 *   public array $items = [];
 *
 * Examples:
 *   #[MapArray(UserDTO::class)]           - Maps array elements to UserDTO instances
 *   #[MapArray(OrderItem::class)]         - Maps array elements to OrderItem instances
 *
 * Features:
 * - Preserves array keys (works with both indexed and associative arrays)
 * - Supports symmetrical mapping (works in both directions)
 * - Gracefully handles empty arrays
 * - Each array element is recursively mapped using the Mapper
 *
 * @param class-string $targetClass The class name to map each array element to
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MapArray
{
    /**
     * @param class-string $targetClass
     */
    public function __construct(
        public readonly string $targetClass
    ) {
    }
}
