<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Fixtures;

use Alecszaharia\Simmap\Attribute\Mappable;

/**
 * Test fixture: Class that maps to NonReciprocalTarget,
 * but NonReciprocalTarget doesn't map back.
 */
#[Mappable(targetClass: NonReciprocalTarget::class)]
final class NonReciprocalSource
{
    public string $value = '';
}
