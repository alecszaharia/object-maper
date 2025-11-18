<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Fixtures;

use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;

#[Mappable(targetClass: CompanyDTO::class)]
final class Company
{
    public string $name = '';

    /**
     * @var array<User>
     */
    #[MapTo(targetProperty: 'employeeDTOs', targetClass: UserDTO::class)]
    public array $employees = [];

    /**
     * @var array<Address>
     */
    #[MapTo(targetProperty: 'locationDTOs', targetClass: AddressDTO::class)]
    public array $locations = [];
}
