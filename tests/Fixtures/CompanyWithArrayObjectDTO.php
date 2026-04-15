<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Fixtures;

use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;
use ArrayObject;

#[Mappable(targetClass: CompanyWithArrayObject::class)]
final class CompanyWithArrayObjectDTO
{
    public ?string $name = null;

    /**
     * @var ArrayObject<int, UserDTO>
     */
    #[MapTo(targetProperty: 'employees', targetClass: User::class)]
    public ?ArrayObject $employeeDTOs = null;

    public function __construct()
    {
        $this->employeeDTOs = new ArrayObject();
    }
}
