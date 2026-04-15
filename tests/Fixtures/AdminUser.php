<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Fixtures;

use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;

#[Mappable(targetClass: UserDTO::class)]
final class AdminUser
{
    public string $email = '';

    #[MapTo(targetProperty: 'fullName')]
    public string $name = '';

    public int $age = 0;

    public string $adminLevel = '';
}
