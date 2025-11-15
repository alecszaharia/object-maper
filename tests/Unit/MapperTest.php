<?php

namespace Alecszaharia\Simmap\Tests\Unit;

use Alecszaharia\Simmap\Exception\MappingException;
use Alecszaharia\Simmap\Mapper;
use Alecszaharia\Simmap\Metadata\MappingMetadata;
use Alecszaharia\Simmap\Metadata\MetadataReader;
use Alecszaharia\Simmap\Metadata\PropertyMapping;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionClass;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class MapperTest extends TestCase
{
    use ProphecyTrait;

    private const TEST_NAME = 'John';
    private const TEST_AGE = 30;
    private const TEST_VALUE = 'test';

    private function createMockPropertyAccessor(): ObjectProphecy
    {
        return $this->prophesize(PropertyAccessorInterface::class);
    }

    private function createMockMetadataReader(
        object $source,
        object $target,
        array $sourceMappings = [],
        array $sourceIgnored = [],
        array $targetMappings = [],
        array $targetIgnored = []
    ): ObjectProphecy {
        $metadataReader = $this->prophesize(MetadataReader::class);

        $sourceMetadata = new MappingMetadata(
            get_class($source),
            $sourceMappings,
            $sourceIgnored,
            new ReflectionClass($source)
        );

        $targetMetadata = new MappingMetadata(
            get_class($target),
            $targetMappings,
            $targetIgnored,
            new ReflectionClass($target)
        );

        $metadataReader->getMetadata($source)->willReturn($sourceMetadata);
        $metadataReader->getMetadata(Argument::type('object'))->willReturn($targetMetadata);

        return $metadataReader;
    }

    /**
     * @dataProvider constructorDependenciesProvider
     */
    public function testConstructor(?PropertyAccessorInterface $accessor, ?MetadataReader $reader): void
    {
        $mapper = new Mapper($accessor, $reader);
        $this->assertInstanceOf(Mapper::class, $mapper);
    }

    public static function constructorDependenciesProvider(): array
    {
        return [
            'default dependencies' => [null, null],
        ];
    }

    public function testMapWithNullTargetReturnsSource(): void
    {
        $source = new class {
            public string $name = 'test';
        };

        $mapper = new Mapper();
        $result = $mapper->map($source, null);

        $this->assertSame($source, $result);
    }

    public function testMapWithObjectTargetMapsProperties(): void
    {
        $source = new class {
            public string $name = 'John';
            public int $age = 30;
        };

        $target = new class {
            public string $name = '';
            public int $age = 0;
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader($source, $target);

        $propertyAccessor->getValue($source, 'name')->willReturn(self::TEST_NAME);
        $propertyAccessor->getValue($source, 'age')->willReturn(self::TEST_AGE);
        $propertyAccessor->isWritable(Argument::type('object'), 'name')->willReturn(true);
        $propertyAccessor->isWritable(Argument::type('object'), 'age')->willReturn(true);
        $propertyAccessor->setValue(Argument::type('object'), 'name', self::TEST_NAME)->shouldBeCalled();
        $propertyAccessor->setValue(Argument::type('object'), 'age', self::TEST_AGE)->shouldBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $target);

        $this->assertSame($target, $result);
    }

    public function testMapWithClassNameCreatesNewInstance(): void
    {
        $source = new class {
            public string $name = 'Jane';
        };

        $targetClass = new class {
            public string $name = '';
        };
        $targetClassName = get_class($targetClass);

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader($source, $targetClass);

        $propertyAccessor->getValue($source, 'name')->willReturn('Jane');
        $propertyAccessor->isWritable(Argument::type('object'), 'name')->willReturn(true);
        $propertyAccessor->setValue(Argument::type('object'), 'name', 'Jane')->shouldBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $targetClassName);

        $this->assertInstanceOf($targetClassName, $result);
        $this->assertNotSame($source, $result);
    }

    public function testMapUsesCustomPropertyMappings(): void
    {
        $source = new class {
            public string $firstName = 'John';
        };

        $target = new class {
            public string $fullName = '';
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader(
            $source,
            $target,
            sourceMappings: [new PropertyMapping('firstName', 'fullName')]
        );

        $propertyAccessor->getValue($source, 'firstName')->willReturn(self::TEST_NAME);
        $propertyAccessor->isWritable(Argument::type('object'), 'fullName')->willReturn(true);
        $propertyAccessor->setValue(Argument::type('object'), 'fullName', self::TEST_NAME)->shouldBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $target);

        $this->assertSame($target, $result);
    }

    public function testMapSupportsReverseMappings(): void
    {
        $source = new class {
            public string $name = 'Bob';
        };

        $target = new class {
            public string $nickname = '';
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader(
            $source,
            $target,
            targetMappings: [new PropertyMapping('nickname', 'name')]
        );

        $propertyAccessor->getValue($source, 'name')->willReturn('Bob');
        $propertyAccessor->isWritable(Argument::type('object'), 'nickname')->willReturn(true);
        $propertyAccessor->setValue(Argument::type('object'), 'nickname', 'Bob')->shouldBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $target);

        $this->assertSame($target, $result);
    }

    public function testMapSupportsNestedTargetPropertyPaths(): void
    {
        $source = new class {
            public string $city = 'New York';
        };

        $address = new class {
            public string $city = '';
        };

        $target = new class {
            public object $address;

            public function __construct()
            {
                $this->address = new class {
                    public string $city = '';
                };
            }
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader(
            $source,
            $target,
            sourceMappings: [new PropertyMapping('city', 'address.city')]
        );

        $propertyAccessor->getValue($source, 'city')->willReturn('New York');
        $propertyAccessor->isWritable(Argument::type('object'), 'address.city')->willReturn(true);
        $propertyAccessor->setValue(Argument::type('object'), 'address.city', 'New York')->shouldBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $target);

        $this->assertSame($target, $result);
    }

    public function testMapSkipsIgnoredPropertiesInSource(): void
    {
        $source = new class {
            public string $name = 'Alice';
            public string $secret = 'hidden';
        };

        $target = new class {
            public string $name = '';
            public string $secret = '';
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader(
            $source,
            $target,
            sourceIgnored: ['secret']
        );

        $propertyAccessor->getValue($source, 'name')->willReturn('Alice');
        $propertyAccessor->getValue($source, 'secret')->shouldNotBeCalled();
        $propertyAccessor->isWritable(Argument::type('object'), 'name')->willReturn(true);
        $propertyAccessor->setValue(Argument::type('object'), 'name', 'Alice')->shouldBeCalled();
        $propertyAccessor->setValue(Argument::type('object'), 'secret', Argument::any())->shouldNotBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $target);

        $this->assertSame($target, $result);
    }

    public function testMapSkipsIgnoredPropertiesInTarget(): void
    {
        $source = new class {
            public string $value = 'test';
        };

        $target = new class {
            public string $value = '';
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader(
            $source,
            $target,
            targetIgnored: ['value']
        );

        $propertyAccessor->getValue($source, 'value')->shouldNotBeCalled();
        $propertyAccessor->setValue(Argument::type('object'), 'value', Argument::any())->shouldNotBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $target);

        $this->assertSame($target, $result);
    }

    public function testMapIgnoresSourcePropertiesMissingFromTarget(): void
    {
        $source = new class {
            public string $name = 'test';
            public string $extraField = 'extra';
        };

        $target = new class {
            public string $name = '';
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader($source, $target);

        $propertyAccessor->getValue($source, 'name')->willReturn(self::TEST_VALUE);
        $propertyAccessor->getValue($source, 'extraField')->shouldNotBeCalled();
        $propertyAccessor->isWritable(Argument::type('object'), 'name')->willReturn(true);
        $propertyAccessor->setValue(Argument::type('object'), 'name', self::TEST_VALUE)->shouldBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $target);

        $this->assertSame($target, $result);
    }

    /**
     * @dataProvider propertyReadExceptionProvider
     */
    public function testMapSkipsPropertiesWithReadErrors(\Throwable $exception): void
    {
        $source = new class {
            public string $accessible = 'value';
            public string $problematic = 'hidden';
        };

        $target = new class {
            public string $accessible = '';
            public string $problematic = '';
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader($source, $target);

        $propertyAccessor->getValue($source, 'accessible')->willReturn('value');
        $propertyAccessor->getValue($source, 'problematic')->willThrow($exception);
        $propertyAccessor->isWritable(Argument::type('object'), 'accessible')->willReturn(true);
        $propertyAccessor->setValue(Argument::type('object'), 'accessible', 'value')->shouldBeCalled();
        $propertyAccessor->setValue(Argument::type('object'), 'problematic', Argument::any())->shouldNotBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $target);

        $this->assertSame($target, $result);
    }

    public static function propertyReadExceptionProvider(): array
    {
        return [
            'uninitialized property' => [
                new UninitializedPropertyException('Property is not initialized')
            ],
            'inaccessible property' => [
                new AccessException('Cannot access property')
            ],
            'non-existent property' => [
                new NoSuchPropertyException('Property does not exist')
            ],
        ];
    }

    public function testMapSkipsNonWritableTargetProperties(): void
    {
        $source = new class {
            public string $name = 'test';
        };

        $target = new class {
            public string $name = '';
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader($source, $target);

        $propertyAccessor->getValue($source, 'name')->willReturn(self::TEST_VALUE);
        $propertyAccessor->isWritable(Argument::type('object'), 'name')->willReturn(false);
        $propertyAccessor->setValue(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());
        $result = $mapper->map($source, $target);

        $this->assertSame($target, $result);
    }

    public function testMapThrowsExceptionWhenPropertyAccessFails(): void
    {
        $source = new class {
            public string $name = 'test';
        };

        $target = new class {
            public string $name = '';
        };

        $propertyAccessor = $this->createMockPropertyAccessor();
        $metadataReader = $this->createMockMetadataReader($source, $target);

        $propertyAccessor->getValue($source, 'name')->willReturn(self::TEST_VALUE);
        $propertyAccessor->isWritable(Argument::type('object'), 'name')->willReturn(true);
        $propertyAccessor->setValue(Argument::type('object'), 'name', self::TEST_VALUE)
            ->willThrow(new AccessException('Cannot write property'));

        $mapper = new Mapper($propertyAccessor->reveal(), $metadataReader->reveal());

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Cannot access property "name" on class');
        $mapper->map($source, $target);
    }

    /**
     * @dataProvider nonInstantiableClassProvider
     */
    public function testMapThrowsExceptionForNonInstantiableClass(string $className): void
    {
        $source = new class {
            public string $name = 'test';
        };

        $mapper = new Mapper();

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Cannot create instance');
        $mapper->map($source, $className);
    }

    public static function nonInstantiableClassProvider(): array
    {
        return [
            'abstract class' => [AbstractTestClass::class],
            'interface' => [TestInterface::class],
            'non-existent class' => ['NonExistentClass'],
        ];
    }
}

// Helper classes for testing
abstract class AbstractTestClass
{
    public string $name = '';
}

interface TestInterface
{
    public function getName(): string;
}
