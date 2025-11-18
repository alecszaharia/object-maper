<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Unit\Metadata;

use Alecszaharia\Simmap\Metadata\MappingMetadata;
use Alecszaharia\Simmap\Metadata\PropertyMapping;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MappingMetadata value object.
 */
final class MappingMetadataTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $mappings = [
            new PropertyMapping('sourceA', 'targetA'),
            new PropertyMapping('sourceB', 'targetB'),
        ];

        $metadata = new MappingMetadata(
            'ClassA',
            'ClassB',
            $mappings,
            true
        );

        $this->assertSame('ClassA', $metadata->classA);
        $this->assertSame('ClassB', $metadata->classB);
        $this->assertSame($mappings, $metadata->propertyMappings);
        $this->assertTrue($metadata->isValidMapping);
    }

    public function testGetMappingsForDirectionMatchingOrder(): void
    {
        $mappings = [
            new PropertyMapping('email', 'email'),
            new PropertyMapping('fullName', 'name'),
        ];

        $metadata = new MappingMetadata('DTO', 'Entity', $mappings, true);

        // When direction matches stored order, return as-is
        $result = $metadata->getMappingsForDirection('DTO', 'Entity');

        $this->assertSame($mappings, $result);
        $this->assertSame('email', $result[0]->sourceProperty);
        $this->assertSame('email', $result[0]->targetProperty);
        $this->assertSame('fullName', $result[1]->sourceProperty);
        $this->assertSame('name', $result[1]->targetProperty);
    }

    public function testGetMappingsForDirectionReversedOrder(): void
    {
        $mappings = [
            new PropertyMapping('email', 'email'),
            new PropertyMapping('fullName', 'name'),
        ];

        $metadata = new MappingMetadata('DTO', 'Entity', $mappings, true);

        // When direction is reversed, mappings should be reversed
        $result = $metadata->getMappingsForDirection('Entity', 'DTO');

        $this->assertCount(2, $result);
        $this->assertSame('email', $result[0]->sourceProperty);
        $this->assertSame('email', $result[0]->targetProperty);
        $this->assertSame('name', $result[1]->sourceProperty);
        $this->assertSame('fullName', $result[1]->targetProperty);
    }

    public function testInvalidMappingFlag(): void
    {
        $metadata = new MappingMetadata('ClassA', 'ClassB', [], false);

        $this->assertFalse($metadata->isValidMapping);
    }
}
