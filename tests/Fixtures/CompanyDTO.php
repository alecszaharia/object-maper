<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Fixtures;

use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;

#[Mappable(targetClass: Company::class)]
final class CompanyDTO
{
    public ?string $name = null;

    /**
     * @var array<UserDTO>
     */
    #[MapTo(targetProperty: 'employees', targetClass: User::class)]
    public ?array $employeeDTOs = null;

    /**
     * @var array<AddressDTO>
     */
    #[MapTo(targetProperty: 'locations', targetClass: Address::class)]
    public ?array $locationDTOs = null;
}
