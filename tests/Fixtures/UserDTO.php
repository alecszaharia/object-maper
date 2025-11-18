<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Fixtures;

use Alecszaharia\Simmap\Attribute\IgnoreMap;
use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;

#[Mappable(targetClass: User::class)]
#[Mappable(targetClass: AdminUser::class)]
final class UserDTO
{
    public string $email = '';

    #[MapTo(targetProperty: 'name')]
    public string $fullName = '';

    public int $age = 0;

    #[IgnoreMap]
    public string $temporaryToken = '';

    #[MapTo(targetProperty: 'profile.bio')]
    public string $biography = '';

    private string $password = '';

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}
