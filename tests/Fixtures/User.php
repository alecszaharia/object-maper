<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Fixtures;

use Alecszaharia\Simmap\Attribute\IgnoreMap;
use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;

#[Mappable(targetClass: UserDTO::class)]
final class User
{
    public string $email = '';

    #[MapTo(targetProperty: 'fullName')]
    public string $name = '';

    public int $age = 0;

    #[IgnoreMap]
    public string $internalId = '';

    public ?Profile $profile = null;

    private string $password = '';

    public function __construct()
    {
        $this->profile = new Profile();
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}
