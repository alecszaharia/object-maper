<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Fixtures;

use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;
use ArrayObject;

#[Mappable(targetClass: CompanyWithArrayObjectDTO::class)]
final class CompanyWithArrayObject
{
    public string $name = '';

    /**
     * @var ArrayObject<int, User>
     */
    #[MapTo(targetProperty: 'employeeDTOs', targetClass: UserDTO::class)]
    public ArrayObject $employees;

    public function __construct()
    {
        $this->employees = new ArrayObject();
    }
}
