<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Fixtures;

use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable(targetClass: Address::class)]
final class AddressDTO
{
    public ?string $street = null;
    public ?string $city = null;
    public ?string $zipCode = null;
}
