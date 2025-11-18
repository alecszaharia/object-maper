<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Unit\Metadata;

use Alecszaharia\Simmap\Metadata\MappingMetadata;
use Alecszaharia\Simmap\Metadata\MetadataReader;
use Alecszaharia\Simmap\Tests\Fixtures\AdminUser;
use Alecszaharia\Simmap\Tests\Fixtures\NonReciprocalSource;
use Alecszaharia\Simmap\Tests\Fixtures\NonReciprocalTarget;
use Alecszaharia\Simmap\Tests\Fixtures\NotMappableClass;
use Alecszaharia\Simmap\Tests\Fixtures\User;
use Alecszaharia\Simmap\Tests\Fixtures\UserDTO;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MetadataReader class.
 *
 * Verifies attribute reading, property mapping detection, and metadata construction.
 */
final class MetadataReaderTest extends TestCase
{
    private MetadataReader $reader;

    protected function setUp(): void
    {
        $this->reader = new MetadataReader();
    }

    public function testBuildMetadataForValidMapping(): void
    {
        $metadata = $this->reader->buildMetadata(UserDTO::class, User::class);

        $this->assertInstanceOf(MappingMetadata::class, $metadata);
        $this->assertSame(UserDTO::class, $metadata->classA);
        $this->assertSame(User::class, $metadata->classB);
        $this->assertTrue($metadata->isValidMapping);
        $this->assertNotEmpty($metadata->propertyMappings);
    }

    public function testMetadataContainsCorrectPropertyMappings(): void
    {
        $metadata = $this->reader->buildMetadata(UserDTO::class, User::class);

        $mappings = $metadata->getMappingsForDirection(UserDTO::class, User::class);

        // Find specific mappings
        $emailMapping = $this->findMapping($mappings, 'email');
        $this->assertNotNull($emailMapping);
        $this->assertSame('email', $emailMapping->sourceProperty);
        $this->assertSame('email', $emailMapping->targetProperty); // Same name

        $fullNameMapping = $this->findMapping($mappings, 'fullName');
        $this->assertNotNull($fullNameMapping);
        $this->assertSame('fullName', $fullNameMapping->sourceProperty);
        $this->assertSame('name', $fullNameMapping->targetProperty); // Custom via #[MapTo]

        $biographyMapping = $this->findMapping($mappings, 'biography');
        $this->assertNotNull($biographyMapping);
        $this->assertSame('biography', $biographyMapping->sourceProperty);
        $this->assertSame('profile.bio', $biographyMapping->targetProperty); // Nested
    }

    public function testIgnoredPropertiesNotInMetadata(): void
    {
        $metadata = $this->reader->buildMetadata(UserDTO::class, User::class);
        $mappings = $metadata->getMappingsForDirection(UserDTO::class, User::class);

        // temporaryToken has #[IgnoreMap]
        $temporaryTokenMapping = $this->findMapping($mappings, 'temporaryToken');
        $this->assertNull($temporaryTokenMapping);
    }

    public function testNonReciprocalMappingIsInvalid(): void
    {
        $metadata = $this->reader->buildMetadata(
            NonReciprocalSource::class,
            NonReciprocalTarget::class
        );

        $this->assertFalse($metadata->isValidMapping);
    }

    public function testBothClassesMustHaveMappableAttribute(): void
    {
        $metadata = $this->reader->buildMetadata(
            UserDTO::class,
            NotMappableClass::class
        );

        $this->assertFalse($metadata->isValidMapping);
    }

    public function testMetadataForMultipleTargets(): void
    {
        // UserDTO has #[Mappable] for both User and AdminUser
        $userMetadata = $this->reader->buildMetadata(UserDTO::class, User::class);
        $adminMetadata = $this->reader->buildMetadata(UserDTO::class, AdminUser::class);

        $this->assertTrue($userMetadata->isValidMapping);
        $this->assertTrue($adminMetadata->isValidMapping);

        // Both should have property mappings
        $this->assertNotEmpty($userMetadata->propertyMappings);
        $this->assertNotEmpty($adminMetadata->propertyMappings);
    }

    public function testReversedMappingsAreCorrect(): void
    {
        $metadata = $this->reader->buildMetadata(UserDTO::class, User::class);

        // Get mappings in both directions
        $forwardMappings = $metadata->getMappingsForDirection(UserDTO::class, User::class);
        $reverseMappings = $metadata->getMappingsForDirection(User::class, UserDTO::class);

        // Count should be the same
        $this->assertCount(count($forwardMappings), $reverseMappings);

        // Find fullName mapping in forward direction
        $forwardFullName = $this->findMapping($forwardMappings, 'fullName');
        $this->assertNotNull($forwardFullName);
        $this->assertSame('fullName', $forwardFullName->sourceProperty);
        $this->assertSame('name', $forwardFullName->targetProperty);

        // In reverse direction, it should be swapped
        $reverseFullName = $this->findMapping($reverseMappings, 'name');
        $this->assertNotNull($reverseFullName);
        $this->assertSame('name', $reverseFullName->sourceProperty);
        $this->assertSame('fullName', $reverseFullName->targetProperty);
    }

    public function testPrivatePropertiesAreIncluded(): void
    {
        $metadata = $this->reader->buildMetadata(UserDTO::class, User::class);
        $mappings = $metadata->getMappingsForDirection(UserDTO::class, User::class);

        // password is private but should be included
        $passwordMapping = $this->findMapping($mappings, 'password');
        $this->assertNotNull($passwordMapping);
        $this->assertSame('password', $passwordMapping->sourceProperty);
        $this->assertSame('password', $passwordMapping->targetProperty);
    }

    /**
     * Helper to find a mapping by source property name.
     *
     * @param array<\Alecszaharia\Simmap\Metadata\PropertyMapping> $mappings
     */
    private function findMapping(array $mappings, string $sourceProperty): ?\Alecszaharia\Simmap\Metadata\PropertyMapping
    {
        foreach ($mappings as $mapping) {
            if ($mapping->sourceProperty === $sourceProperty) {
                return $mapping;
            }
        }
        return null;
    }
}
