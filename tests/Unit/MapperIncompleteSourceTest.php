<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Unit;

use Alecszaharia\Simmap\Mapper;
use Alecszaharia\Simmap\Tests\Fixtures\Address;
use Alecszaharia\Simmap\Tests\Fixtures\AddressDTO;
use Alecszaharia\Simmap\Tests\Fixtures\Company;
use Alecszaharia\Simmap\Tests\Fixtures\CompanyDTO;
use Alecszaharia\Simmap\Tests\Fixtures\User;
use Alecszaharia\Simmap\Tests\Fixtures\UserDTO;
use PHPUnit\Framework\TestCase;

/**
 * Tests for mapping incomplete source objects.
 *
 * These tests verify that the Mapper correctly handles source objects with:
 * - Null property values
 * - Uninitialized properties
 * - Sparse/partial data
 *
 * The expected behavior is to skip null/undefined properties during mapping,
 * allowing partial object updates without overwriting existing target values.
 */
final class MapperIncompleteSourceTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new Mapper();
    }

    /**
     * Test mapping from completely uninitialized source.
     */
    public function testCompletelyUninitializedSource(): void
    {
        // Create target with values
        $user = new User();
        $user->email = 'keep@example.com';
        $user->name = 'Keep Name';
        $user->age = 30;

        // Create completely uninitialized source
        $dto = new UserDTO();
        // All properties are null by default

        // Map to target
        $this->mapper->map($dto, $user);

        // All target properties should remain unchanged
        $this->assertSame('keep@example.com', $user->email);
        $this->assertSame('Keep Name', $user->name);
        $this->assertSame(30, $user->age);
    }

    /**
     * Test mixed initialized and uninitialized properties.
     * Verifies that some properties can be updated while others remain unchanged.
     */
    public function testMixedInitializedProperties(): void
    {
        $user = new User();
        $user->email = 'original@example.com';
        $user->name = 'Original Name';
        $user->age = 35;

        $dto = new UserDTO();
        $dto->email = null; // Not updating
        $dto->fullName = 'Updated Name'; // Updating
        $dto->age = null; // Not updating

        $this->mapper->map($dto, $user);

        $this->assertSame('original@example.com', $user->email); // Unchanged
        $this->assertSame('Updated Name', $user->name); // Updated
        $this->assertSame(35, $user->age); // Unchanged
    }

    /**
     * Test nested property updates with partial data.
     * Verifies both null nested properties and selective nested updates.
     */
    public function testNestedPropertiesWithPartialUpdate(): void
    {
        $user = new User();
        $user->email = 'nested@example.com';
        $user->name = 'Nested User';
        $user->profile->bio = 'Original bio';
        $user->profile->avatar = 'original_avatar.png';

        // Update only the bio via nested property, null should not overwrite avatar
        $dto = new UserDTO();
        $dto->biography = 'Updated bio'; // This maps to profile.bio
        // All other properties are null

        $this->mapper->map($dto, $user);

        // Only bio should change
        $this->assertSame('Updated bio', $user->profile->bio);
        $this->assertSame('original_avatar.png', $user->profile->avatar); // Unchanged
    }

    /**
     * Test array mapping with incomplete sources.
     */
    public function testArrayMappingWithIncompleteSource(): void
    {
        // Create company with existing employees
        $company = new Company();
        $company->name = 'Existing Corp';

        $emp1 = new User();
        $emp1->email = 'emp1@example.com';
        $emp1->name = 'Employee One';
        $emp1->age = 30;

        $company->employees = [$emp1];

        // Create DTO with null employees array
        $dto = new CompanyDTO();
        $dto->name = 'Updated Corp';
        $dto->employeeDTOs = null; // Should not overwrite employees

        $this->mapper->map($dto, $company);

        // Name should update but employees should remain
        $this->assertSame('Updated Corp', $company->name);
        $this->assertCount(1, $company->employees);
        $this->assertSame('emp1@example.com', $company->employees[0]->email);
    }

    /**
     * Test that empty arrays (not null) do overwrite.
     * This ensures we distinguish between null (skip) and empty array (valid value).
     */
    public function testEmptyArrayDoesOverwrite(): void
    {
        $company = new Company();
        $company->name = 'Has Employees';

        $emp1 = new User();
        $emp1->email = 'emp@example.com';
        $emp1->name = 'Employee';
        $company->employees = [$emp1];

        // Explicitly set to empty array (not null)
        $dto = new CompanyDTO();
        $dto->name = 'Has Employees';
        $dto->employeeDTOs = []; // Empty but not null

        $this->mapper->map($dto, $company);

        // Empty array should replace existing employees
        $this->assertIsArray($company->employees);
        $this->assertCount(0, $company->employees);
    }

    /**
     * Test backwards compatibility: mapping to new instance works normally.
     */
    public function testNewInstanceMappingStillWorks(): void
    {
        // This ensures backward compatibility when mapping to a new instance
        $dto = new UserDTO();
        $dto->email = 'new@example.com';
        $dto->fullName = 'New User';
        $dto->age = 28;

        $user = $this->mapper->map($dto, User::class);

        $this->assertSame('new@example.com', $user->email);
        $this->assertSame('New User', $user->name);
        $this->assertSame(28, $user->age);
    }

    /**
     * Test that falsy but valid values (zero, empty string) are still mapped.
     * Ensures we distinguish between null (skip) and falsy values (map).
     */
    public function testFalsyButValidValuesAreMapped(): void
    {
        $user = new User();
        $user->email = 'old@example.com';
        $user->name = 'Old Name';
        $user->age = 100; // Non-zero age

        $dto = new UserDTO();
        $dto->email = ''; // Empty string is valid
        $dto->fullName = ''; // Empty string is valid
        $dto->age = 0; // Zero is a valid value

        $this->mapper->map($dto, $user);

        // All falsy values should be mapped (they're not null)
        $this->assertSame('', $user->email);
        $this->assertSame('', $user->name);
        $this->assertSame(0, $user->age);
    }

    /**
     * Test private properties with null values.
     */
    public function testPrivatePropertiesWithNullValues(): void
    {
        $user = new User();
        $user->email = 'test@example.com';
        $user->name = 'Test';
        $user->setPassword('original_password');

        $dto = new UserDTO();
        $dto->email = 'updated@example.com';
        // password is null (via setter it would be null)

        $this->mapper->map($dto, $user);

        // Password should remain unchanged
        $this->assertSame('original_password', $user->getPassword());
    }

    /**
     * Test real-world scenario: API response with sparse data.
     */
    public function testApiSparseResponse(): void
    {
        // Simulating an existing user entity from database
        $user = new User();
        $user->email = 'user@db.com';
        $user->name = 'DB User';
        $user->age = 45;
        $user->profile->bio = 'Database biography';
        $user->setPassword('hashed_password');

        // API response only returns email and name (sparse data)
        $apiResponse = new UserDTO();
        $apiResponse->email = 'user@api.com';
        $apiResponse->fullName = 'API User';
        // age, biography, password are all null in the response

        // Update entity with API data
        $this->mapper->map($apiResponse, $user);

        // Only returned fields should update
        $this->assertSame('user@api.com', $user->email);
        $this->assertSame('API User', $user->name);

        // Unreturned fields should preserve database values
        $this->assertSame(45, $user->age);
        $this->assertSame('Database biography', $user->profile->bio);
        $this->assertSame('hashed_password', $user->getPassword());
    }

    /**
     * Test complex nested object update with partial data.
     */
    public function testComplexNestedPartialUpdate(): void
    {
        $company = new Company();
        $company->name = 'Tech Company';

        $address1 = new Address();
        $address1->street = '123 Main St';
        $address1->city = 'New York';
        $address1->zipCode = '10001';

        $company->locations = [$address1];

        $user1 = new User();
        $user1->email = 'emp1@tech.com';
        $user1->name = 'Employee 1';
        $user1->age = 30;

        $company->employees = [$user1];

        // Partial update: only update company name
        $dto = new CompanyDTO();
        $dto->name = 'Updated Tech Company';
        // employeeDTOs and locationDTOs are null

        $this->mapper->map($dto, $company);

        // Only name should change
        $this->assertSame('Updated Tech Company', $company->name);

        // Nested collections should remain intact
        $this->assertCount(1, $company->locations);
        $this->assertSame('123 Main St', $company->locations[0]->street);
        $this->assertCount(1, $company->employees);
        $this->assertSame('emp1@tech.com', $company->employees[0]->email);
    }
}
