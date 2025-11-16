<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Unit\Metadata;

use Alecszaharia\Simmap\Attribute\Ignore;
use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;
use Alecszaharia\Simmap\Metadata\MetadataReader;
use PHPUnit\Framework\TestCase;

class MetadataReaderTest extends TestCase
{
    public function testDetectsMappableAttribute(): void
    {
        $reader = new MetadataReader();

        $mappableMetadata = $reader->getMetadata(MappableTestClass::class);
        $this->assertTrue($mappableMetadata->isMappable);

        $nonMappableMetadata = $reader->getMetadata(NonMappableTestClass::class);
        $this->assertFalse($nonMappableMetadata->isMappable);
    }

    public function testReadsMapToAttribute(): void
    {
        $reader = new MetadataReader();
        $metadata = $reader->getMetadata(ClassWithMapTo::class);

        $this->assertSame('targetName', $metadata->findTargetProperty('name'));
    }
    public function testReadsMapToAttributeWithNullTargetProperty(): void
    {
        $reader = new MetadataReader();
        $metadata = $reader->getMetadata(ClassWithMapToDefaultToPropertyName::class);

        $this->assertSame('name', $metadata->findTargetProperty('name'));
    }

    public function testReadsIgnoreAttribute(): void
    {
        $reader = new MetadataReader();
        $metadata = $reader->getMetadata(ClassWithIgnored::class);

        $this->assertTrue($metadata->isPropertyIgnored('ignored'));
        $this->assertFalse($metadata->isPropertyIgnored('notIgnored'));
    }

    public function testCachesAndClearsCacheCorrectly(): void
    {
        $reader = new MetadataReader();

        // Test that metadata is cached
        $metadata1 = $reader->getMetadata(CachedTestClass::class);
        $metadata2 = $reader->getMetadata(CachedTestClass::class);
        $this->assertSame($metadata1, $metadata2);

        // Test that clearCache removes cached data
        $reader->clearCache();
        $metadata3 = $reader->getMetadata(CachedTestClass::class);
        $this->assertNotSame($metadata1, $metadata3);
    }

    public function testReadsMetadataFromObjectInstance(): void
    {
        $reader = new MetadataReader();
        $instance = new MappableTestClass();
        $metadata = $reader->getMetadata($instance);

        $this->assertSame(MappableTestClass::class, $metadata->className);
    }

    public function testMappableAttributeIsNotInherited(): void
    {
        $reader = new MetadataReader();
        $parentMetadata = $reader->getMetadata(MappableParentClass::class);
        $childMetadata = $reader->getMetadata(NonMappableChildClass::class);

        $this->assertTrue($parentMetadata->isMappable);
        $this->assertFalse($childMetadata->isMappable);
    }
}

// Test fixture classes
#[Mappable]
class MappableTestClass
{
    public string $name;
}

class NonMappableTestClass
{
    public string $name;
}

#[Mappable]
class ClassWithMapTo
{
    #[MapTo('targetName')]
    public string $name;
}

#[Mappable]
class ClassWithMapToDefaultToPropertyName
{
    #[MapTo]
    public string $name;
}

#[Mappable]
class ClassWithIgnored
{
    #[Ignore]
    public string $ignored;

    public string $notIgnored;
}

#[Mappable]
class CachedTestClass
{
    public string $name;
}

#[Mappable]
class MappableParentClass
{
    public string $parentProp;
}

// Child class does not have #[Mappable]
class NonMappableChildClass extends MappableParentClass
{
    public string $childProp;
}
