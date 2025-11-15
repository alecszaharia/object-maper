<?php

namespace Alecszaharia\Simmap\Attribute;

use Attribute;

/**
 * Marks a property to be excluded from automatic mapping.
 *
 * When this attribute is present on a property, the mapper will skip it
 * even if it would normally be auto-mapped due to matching names.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Ignore
{
}
