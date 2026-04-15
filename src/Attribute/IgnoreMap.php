<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Attribute;

use Attribute;

/**
 * Excludes a property from being mapped.
 *
 * Use this attribute when you want to prevent a property from being
 * included in the mapping process, even if it has the same name
 * in both source and target classes.
 *
 * @example
 * #[IgnoreMap]
 * private string $temporaryValue;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class IgnoreMap
{
}
