<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Unit\Metadata;

use Alecszaharia\Simmap\Metadata\PropertyMapping;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PropertyMapping value object.
 */
final class PropertyMappingTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $mapping = new PropertyMapping('sourceProperty', 'targetProperty');

        $this->assertSame('sourceProperty', $mapping->sourceProperty);
        $this->assertSame('targetProperty', $mapping->targetProperty);
    }

    public function testReverse(): void
    {
        $mapping = new PropertyMapping('fullName', 'name');
        $reversed = $mapping->reverse();

        $this->assertSame('name', $reversed->sourceProperty);
        $this->assertSame('fullName', $reversed->targetProperty);

        // Original should be unchanged
        $this->assertSame('fullName', $mapping->sourceProperty);
        $this->assertSame('name', $mapping->targetProperty);
    }

    public function testReverseWithNestedPath(): void
    {
        $mapping = new PropertyMapping('biography', 'profile.bio');
        $reversed = $mapping->reverse();

        $this->assertSame('profile.bio', $reversed->sourceProperty);
        $this->assertSame('biography', $reversed->targetProperty);
    }

    public function testDoubleReverse(): void
    {
        $original = new PropertyMapping('email', 'emailAddress');
        $reversed = $original->reverse();
        $doubleReversed = $reversed->reverse();

        // Double reverse should match original
        $this->assertSame($original->sourceProperty, $doubleReversed->sourceProperty);
        $this->assertSame($original->targetProperty, $doubleReversed->targetProperty);
    }
}
