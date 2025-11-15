<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Unit\Metadata;

use Alecszaharia\Simmap\Metadata\MappingMetadata;
use Alecszaharia\Simmap\Metadata\PropertyMapping;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MappingMetadataTest extends TestCase
{
    public function testConstructorWithClassName(): void
    {
        $metadata = new MappingMetadata(
            className: self::class,
            mappings: [],
            ignoredProperties: []
        );

        $this->assertSame(self::class, $metadata->className);
        $this->assertInstanceOf(ReflectionClass::class, $metadata->reflection);
        $this->assertSame(self::class, $metadata->reflection->getName());
    }

    public function testConstructorWithReflection(): void
    {
        $reflection = new ReflectionClass(self::class);
        $metadata = new MappingMetadata(
            className: self::class,
            mappings: [],
            ignoredProperties: [],
            reflection: $reflection
        );

        $this->assertSame($reflection, $metadata->reflection);
    }

    public function testConstructorWithPrePopulatedMappings(): void
    {
        $mappings = [
            new PropertyMapping('name', 'fullName'),
            new PropertyMapping('email', 'emailAddress'),
            new PropertyMapping('age', 'userAge'),
        ];

        $metadata = new MappingMetadata(
            className: self::class,
            mappings: $mappings
        );

        $this->assertSame($mappings, $metadata->getMappings());

        // Verify forward index (source -> target)
        $this->assertSame('fullName', $metadata->findTargetProperty('name'));
        $this->assertSame('emailAddress', $metadata->findTargetProperty('email'));
        $this->assertSame('userAge', $metadata->findTargetProperty('age'));

        // Verify reverse index (target -> source)
        $this->assertSame('name', $metadata->findSourceProperty('fullName'));
        $this->assertSame('email', $metadata->findSourceProperty('emailAddress'));
        $this->assertSame('age', $metadata->findSourceProperty('userAge'));
    }

    public function testConstructorWithPrePopulatedIgnoredProperties(): void
    {
        $ignored = ['secret', 'internal', 'temp'];

        $metadata = new MappingMetadata(
            className: self::class,
            ignoredProperties: $ignored
        );

        $result = $metadata->getIgnoredProperties();
        sort($result);
        sort($ignored);

        $this->assertSame($ignored, $result);
        $this->assertTrue($metadata->isPropertyIgnored('secret'));
        $this->assertTrue($metadata->isPropertyIgnored('internal'));
        $this->assertTrue($metadata->isPropertyIgnored('temp'));
    }

    public function testAddMappingSuccessfully(): void
    {
        $metadata = new MappingMetadata(self::class);

        $mapping1 = new PropertyMapping('firstName', 'first');
        $mapping2 = new PropertyMapping('lastName', 'last');

        $metadata->addMapping($mapping1);
        $metadata->addMapping($mapping2);

        $this->assertContains($mapping1, $metadata->getMappings());
        $this->assertContains($mapping2, $metadata->getMappings());

        // Verify forward index (source -> target)
        $this->assertSame('first', $metadata->findTargetProperty('firstName'));
        $this->assertSame('last', $metadata->findTargetProperty('lastName'));

        // Verify reverse index (target -> source)
        $this->assertSame('firstName', $metadata->findSourceProperty('first'));
        $this->assertSame('lastName', $metadata->findSourceProperty('last'));
    }

    public function testAddMappingWithDuplicateSourcePropertyThrowsException(): void
    {
        $metadata = new MappingMetadata(self::class);
        $metadata->addMapping(new PropertyMapping('name', 'fullName'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate source property "name"');

        $metadata->addMapping(new PropertyMapping('name', 'displayName'));
    }

    public function testAddMappingWithDuplicateTargetPropertyThrowsException(): void
    {
        $metadata = new MappingMetadata(self::class);
        $metadata->addMapping(new PropertyMapping('name', 'fullName'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate target property "fullName"');

        $metadata->addMapping(new PropertyMapping('alias', 'fullName'));
    }

    public function testAddIgnoredProperty(): void
    {
        $metadata = new MappingMetadata(self::class);

        $metadata->addIgnoredProperty('secret');
        $metadata->addIgnoredProperty('internal');

        $this->assertTrue($metadata->isPropertyIgnored('secret'));
        $this->assertTrue($metadata->isPropertyIgnored('internal'));
        $this->assertFalse($metadata->isPropertyIgnored('public'));
    }

    public function testAddIgnoredPropertyWithDuplicatesSilentlySkips(): void
    {
        $metadata = new MappingMetadata(self::class);

        $metadata->addIgnoredProperty('secret');
        $metadata->addIgnoredProperty('secret'); // Duplicate
        $metadata->addIgnoredProperty('secret'); // Another duplicate

        $ignored = $metadata->getIgnoredProperties();
        $this->assertCount(1, $ignored);
        $this->assertContains('secret', $ignored);
    }

    public function testGetMappings(): void
    {
        $metadata = new MappingMetadata(self::class);
        $mapping1 = new PropertyMapping('a', 'b');
        $mapping2 = new PropertyMapping('c', 'd');

        $metadata->addMapping($mapping1);
        $metadata->addMapping($mapping2);

        $mappings = $metadata->getMappings();
        $this->assertCount(2, $mappings);
        $this->assertContains($mapping1, $mappings);
        $this->assertContains($mapping2, $mappings);
    }

    public function testGetIgnoredProperties(): void
    {
        $metadata = new MappingMetadata(self::class);

        $metadata->addIgnoredProperty('a');
        $metadata->addIgnoredProperty('b');
        $metadata->addIgnoredProperty('c');

        $ignored = $metadata->getIgnoredProperties();
        sort($ignored);

        $this->assertSame(['a', 'b', 'c'], $ignored);
    }

    public function testIsPropertyIgnoredReturnsFalseForNonIgnoredProperties(): void
    {
        $metadata = new MappingMetadata(self::class);
        $metadata->addIgnoredProperty('ignored');

        $this->assertTrue($metadata->isPropertyIgnored('ignored'));
        $this->assertFalse($metadata->isPropertyIgnored('notIgnored'));
        $this->assertFalse($metadata->isPropertyIgnored(''));
    }

    public function testFindTargetPropertyReturnsNullWhenNotFound(): void
    {
        $metadata = new MappingMetadata(self::class);
        $metadata->addMapping(new PropertyMapping('name', 'fullName'));

        $this->assertNull($metadata->findTargetProperty('nonExistent'));
    }

    public function testFindSourcePropertyReturnsNullWhenNotFound(): void
    {
        $metadata = new MappingMetadata(self::class);
        $metadata->addMapping(new PropertyMapping('name', 'fullName'));

        $this->assertNull($metadata->findSourceProperty('nonExistent'));
    }

    public function testFindTargetPropertyWithNestedPaths(): void
    {
        $metadata = new MappingMetadata(self::class);
        $metadata->addMapping(new PropertyMapping('city', 'address.city'));
        $metadata->addMapping(new PropertyMapping('zip', 'address.zipCode'));

        $this->assertSame('address.city', $metadata->findTargetProperty('city'));
        $this->assertSame('address.zipCode', $metadata->findTargetProperty('zip'));
    }

    public function testSymmetricalMappingWithNestedPaths(): void
    {
        $metadata = new MappingMetadata(self::class);
        $metadata->addMapping(new PropertyMapping('city', 'address.city'));

        // Forward
        $this->assertSame('address.city', $metadata->findTargetProperty('city'));

        // Reverse
        $this->assertSame('city', $metadata->findSourceProperty('address.city'));
    }

    public function testEmptyMetadata(): void
    {
        $metadata = new MappingMetadata(self::class);

        $this->assertEmpty($metadata->getMappings());
        $this->assertEmpty($metadata->getIgnoredProperties());
        $this->assertNull($metadata->findTargetProperty('anything'));
        $this->assertNull($metadata->findSourceProperty('anything'));
        $this->assertFalse($metadata->isPropertyIgnored('anything'));
    }

    public function testConstructorThrowsOnDuplicateSourcePropertyInMappings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate source property "name"');

        new MappingMetadata(
            className: self::class,
            mappings: [
                new PropertyMapping('name', 'fullName'),
                new PropertyMapping('name', 'displayName'), // Duplicate source
            ]
        );
    }

    public function testConstructorThrowsOnDuplicateTargetPropertyInMappings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate target property "fullName"');

        new MappingMetadata(
            className: self::class,
            mappings: [
                new PropertyMapping('name', 'fullName'),
                new PropertyMapping('alias', 'fullName'), // Duplicate target
            ]
        );
    }

    public function testConstructorThrowsOnInvalidMappingType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All mappings must be instances of PropertyMapping');

        new MappingMetadata(
            className: self::class,
            mappings: [
                new PropertyMapping('name', 'fullName'),
                'invalid', // Not a PropertyMapping object
            ]
        );
    }

    public function testConstructorThrowsOnInvalidIgnoredPropertyType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All ignored properties must be strings');

        new MappingMetadata(
            className: self::class,
            ignoredProperties: [
                'valid',
                123, // Not a string
            ]
        );
    }

    public function testConstructorThrowsOnInvalidClassName(): void
    {
        $this->expectException(\ReflectionException::class);

        new MappingMetadata(
            className: 'NonExistentClass\\That\\Does\\Not\\Exist'
        );
    }

    public function testConstructorWithIsMappableTrue(): void
    {
        $metadata = new MappingMetadata(
            className: self::class,
            isMappable: true
        );

        $this->assertTrue($metadata->isMappable);
    }

    public function testConstructorWithIsMappableFalse(): void
    {
        $metadata = new MappingMetadata(
            className: self::class,
            isMappable: false
        );

        $this->assertFalse($metadata->isMappable);
    }

    public function testConstructorDefaultsIsMappableToFalse(): void
    {
        $metadata = new MappingMetadata(
            className: self::class
        );

        $this->assertFalse($metadata->isMappable);
    }

    public function testPerformanceOfFindOperationsIsConstantTime(): void
    {
        $metadata = new MappingMetadata(self::class);

        // Add many mappings to test O(1) performance
        for ($i = 0; $i < 1000; $i++) {
            $metadata->addMapping(new PropertyMapping("source$i", "target$i"));
        }

        // These operations should be O(1) regardless of number of mappings
        // Testing that lookups work correctly with large datasets
        $this->assertSame('target500', $metadata->findTargetProperty('source500'));
        $this->assertSame('target999', $metadata->findTargetProperty('source999'));
        $this->assertSame('target0', $metadata->findTargetProperty('source0'));

        // Reverse lookups should also be O(1)
        $this->assertSame('source500', $metadata->findSourceProperty('target500'));
        $this->assertSame('source999', $metadata->findSourceProperty('target999'));
    }

    public function testPerformanceOfIsPropertyIgnoredIsConstantTime(): void
    {
        $metadata = new MappingMetadata(self::class);

        // Add many ignored properties
        for ($i = 0; $i < 1000; $i++) {
            $metadata->addIgnoredProperty("property$i");
        }

        // These operations should be O(1) via isset()
        // Testing that lookups work correctly with large datasets
        $this->assertTrue($metadata->isPropertyIgnored('property500'));
        $this->assertTrue($metadata->isPropertyIgnored('property999'));
        $this->assertTrue($metadata->isPropertyIgnored('property0'));
        $this->assertFalse($metadata->isPropertyIgnored('nonExistent'));
    }

    public function testEdgeCaseWithEmptyStringPropertyNames(): void
    {
        $metadata = new MappingMetadata(self::class);

        // Empty strings are technically valid property names in the metadata system
        // (though not valid in actual PHP classes)
        $metadata->addMapping(new PropertyMapping('', 'target'));
        $metadata->addIgnoredProperty('');

        $this->assertSame('target', $metadata->findTargetProperty(''));
        $this->assertTrue($metadata->isPropertyIgnored(''));
    }
}
