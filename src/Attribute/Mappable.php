<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Attribute;

use Attribute;

/**
 * Marks a class as eligible for object mapping.
 *
 * Both source and target classes must have this attribute for mapping to occur.
 * This provides explicit opt-in control over which classes can participate in mapping operations.
 *
 * Example:
 *   #[Mappable]
 *   class UserDTO {
 *       public string $name;
 *   }
 *
 *   #[Mappable]
 *   class UserEntity {
 *       public string $name;
 *   }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Mappable
{
}
