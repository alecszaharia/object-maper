<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Unit;

use Alecszaharia\Simmap\Exception\MappingException;
use Alecszaharia\Simmap\Mapper;
use Alecszaharia\Simmap\Tests\Fixtures\Address;
use Alecszaharia\Simmap\Tests\Fixtures\AddressDTO;
use Alecszaharia\Simmap\Tests\Fixtures\AdminUser;
use Alecszaharia\Simmap\Tests\Fixtures\Company;
use Alecszaharia\Simmap\Tests\Fixtures\CompanyDTO;
use Alecszaharia\Simmap\Tests\Fixtures\CompanyWithArrayObject;
use Alecszaharia\Simmap\Tests\Fixtures\CompanyWithArrayObjectDTO;
use Alecszaharia\Simmap\Tests\Fixtures\NonReciprocalSource;
use Alecszaharia\Simmap\Tests\Fixtures\NonReciprocalTarget;
use Alecszaharia\Simmap\Tests\Fixtures\NotMappableClass;
use Alecszaharia\Simmap\Tests\Fixtures\Profile;
use Alecszaharia\Simmap\Tests\Fixtures\User;
use Alecszaharia\Simmap\Tests\Fixtures\UserDTO;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for the Mapper class.
 *
 * Tests cover:
 * - Bidirectional mapping with same property names
 * - Custom property name mapping via #[MapTo]
 * - Nested property paths with dot notation
 * - Property exclusion via #[IgnoreMap]
 * - Multiple target classes on single source
 * - Metadata caching behavior
 * - Target class auto-instantiation
 * - Private property access via getters/setters
 * - Error cases and validation
 */
final class MapperTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new Mapper();
    }


    public function testMappingToExistingInstance(): void
    {
        $dto = new UserDTO();
        $dto->email = 'test@example.com';
        $dto->fullName = 'John Doe';
        $dto->age = 30;

        $user = new User();
        $user->internalId = 'preserve-me';

        $result = $this->mapper->map($dto, $user);

        $this->assertSame($user, $result);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame(30, $user->age);
        $this->assertSame('preserve-me', $user->internalId); // Should not be overwritten
    }


    public function testIgnoredPropertiesAreNotMapped(): void
    {
        $dto = new UserDTO();
        $dto->email = 'test@example.com';
        $dto->temporaryToken = 'secret-token';

        $user = $this->mapper->map($dto, User::class);

        // temporaryToken has #[IgnoreMap], should not affect user
        $this->assertSame('test@example.com', $user->email);

        // internalId on User also has #[IgnoreMap]
        $user->internalId = 'internal-value';

        // Map back to the same DTO instance to preserve ignored properties
        $dtoBack = $dto;
        $this->mapper->map($user, $dtoBack);
        // temporaryToken should remain unchanged because it has #[IgnoreMap]
        $this->assertSame('secret-token', $dtoBack->temporaryToken);
    }


    public function testMappingToMultipleTargetClasses(): void
    {
        $dto = new UserDTO();
        $dto->email = 'admin@example.com';
        $dto->fullName = 'Admin User';
        $dto->age = 35;

        // UserDTO can map to both User and AdminUser
        $user = $this->mapper->map($dto, User::class);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Admin User', $user->name);

        $adminUser = $this->mapper->map($dto, AdminUser::class);
        $this->assertInstanceOf(AdminUser::class, $adminUser);
        $this->assertSame('Admin User', $adminUser->name);
    }


    public function testTargetRequiredException(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Target parameter is required');

        $dto = new UserDTO();
        $this->mapper->map($dto, null);
    }

    public function testNonReciprocalMappingThrowsException(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Non-reciprocal mapping detected');

        $source = new NonReciprocalSource();
        $this->mapper->map($source, NonReciprocalTarget::class);
    }

    public function testMappingNotMappableClassThrowsException(): void
    {
        $this->expectException(MappingException::class);

        $source = new NotMappableClass();
        $target = new UserDTO();

        $this->mapper->map($source, $target);
    }


    public function testCompleteWorkflowWithAllFeatures(): void
    {
        // Create a DTO with all types of properties
        $dto = new UserDTO();
        $dto->email = 'complete@example.com';
        $dto->fullName = 'Complete Test';
        $dto->age = 42;
        $dto->temporaryToken = 'should-be-ignored';
        $dto->biography = 'Full stack developer';
        $dto->setPassword('secret');

        // Map to User
        $user = $this->mapper->map($dto, User::class);

        // Verify all mappings
        $this->assertSame('complete@example.com', $user->email); // Same name
        $this->assertSame('Complete Test', $user->name); // Custom mapping
        $this->assertSame(42, $user->age); // Same name
        $this->assertSame('Full stack developer', $user->profile->bio); // Nested
        $this->assertSame('secret', $user->getPassword()); // Private property

        // Modify user and map back to the original DTO instance
        $user->email = 'modified@example.com';
        $user->name = 'Modified Name';
        $user->age = 43;
        $user->profile->bio = 'Updated bio';
        $user->setPassword('newsecret');
        $user->internalId = 'should-not-map';

        // Reuse the same DTO instance to preserve ignored properties
        $dtoBack = $dto;
        $this->mapper->map($user, $dtoBack);

        // Verify reverse mappings
        $this->assertSame('modified@example.com', $dtoBack->email);
        $this->assertSame('Modified Name', $dtoBack->fullName);
        $this->assertSame(43, $dtoBack->age);
        $this->assertSame('Updated bio', $dtoBack->biography);
        $this->assertSame('newsecret', $dtoBack->getPassword());
        $this->assertSame('should-be-ignored', $dtoBack->temporaryToken); // Unchanged because #[IgnoreMap]
    }

    public function testNullValuesAreMapped(): void
    {
        $dto = new UserDTO();
        $dto->email = 'null-test@example.com';
        $dto->biography = '';

        $user = $this->mapper->map($dto, User::class);

        // Empty string should be mapped
        $this->assertSame('', $user->profile->bio);

        // Test with actual nullable property
        $user->profile->avatar = null;
        $dtoBack = $this->mapper->map($user, new UserDTO());

        // Should handle null gracefully
        $this->assertInstanceOf(UserDTO::class, $dtoBack);
    }

    // ===== Array Mapping Tests =====

    public function testBasicArrayMapping(): void
    {
        // Create DTOs with employees
        $dto = new CompanyDTO();
        $dto->name = 'Tech Corp';

        $emp1 = new UserDTO();
        $emp1->email = 'john@techcorp.com';
        $emp1->fullName = 'John Doe';
        $emp1->age = 30;

        $emp2 = new UserDTO();
        $emp2->email = 'jane@techcorp.com';
        $emp2->fullName = 'Jane Smith';
        $emp2->age = 28;

        $dto->employeeDTOs = [$emp1, $emp2];

        // Map to Company entity
        $company = $this->mapper->map($dto, Company::class);

        $this->assertSame('Tech Corp', $company->name);
        $this->assertCount(2, $company->employees);
        $this->assertInstanceOf(User::class, $company->employees[0]);
        $this->assertInstanceOf(User::class, $company->employees[1]);
        $this->assertSame('john@techcorp.com', $company->employees[0]->email);
        $this->assertSame('John Doe', $company->employees[0]->name);
        $this->assertSame('jane@techcorp.com', $company->employees[1]->email);
        $this->assertSame('Jane Smith', $company->employees[1]->name);
    }

    public function testBidirectionalArrayMapping(): void
    {
        // Create Company with employees
        $company = new Company();
        $company->name = 'Acme Inc';

        $user1 = new User();
        $user1->email = 'alice@acme.com';
        $user1->name = 'Alice Johnson';
        $user1->age = 35;

        $user2 = new User();
        $user2->email = 'bob@acme.com';
        $user2->name = 'Bob Wilson';
        $user2->age = 42;

        $company->employees = [$user1, $user2];

        // Map to DTO
        $dto = $this->mapper->map($company, CompanyDTO::class);

        $this->assertSame('Acme Inc', $dto->name);
        $this->assertCount(2, $dto->employeeDTOs);
        $this->assertInstanceOf(UserDTO::class, $dto->employeeDTOs[0]);
        $this->assertInstanceOf(UserDTO::class, $dto->employeeDTOs[1]);
        $this->assertSame('alice@acme.com', $dto->employeeDTOs[0]->email);
        $this->assertSame('Alice Johnson', $dto->employeeDTOs[0]->fullName);
        $this->assertSame('bob@acme.com', $dto->employeeDTOs[1]->email);
        $this->assertSame('Bob Wilson', $dto->employeeDTOs[1]->fullName);

        // Map back to Company
        $companyBack = $this->mapper->map($dto, Company::class);

        $this->assertSame('Acme Inc', $companyBack->name);
        $this->assertCount(2, $companyBack->employees);
        $this->assertSame('alice@acme.com', $companyBack->employees[0]->email);
        $this->assertSame('Alice Johnson', $companyBack->employees[0]->name);
    }

    public function testEmptyArrayMapping(): void
    {
        $dto = new CompanyDTO();
        $dto->name = 'Empty Corp';
        $dto->employeeDTOs = [];

        $company = $this->mapper->map($dto, Company::class);

        $this->assertSame('Empty Corp', $company->name);
        $this->assertIsArray($company->employees);
        $this->assertCount(0, $company->employees);
    }

    public function testArrayWithNullItems(): void
    {
        $dto = new CompanyDTO();
        $dto->name = 'Null Corp';

        $emp1 = new UserDTO();
        $emp1->email = 'exists@null.com';
        $emp1->fullName = 'Exists User';

        // Array with null item
        $dto->employeeDTOs = [$emp1, null];

        $company = $this->mapper->map($dto, Company::class);

        $this->assertCount(2, $company->employees);
        $this->assertInstanceOf(User::class, $company->employees[0]);
        $this->assertNull($company->employees[1]);
    }

    public function testNestedArrayMapping(): void
    {
        // Create company with multiple locations
        $dto = new CompanyDTO();
        $dto->name = 'Global Corp';

        $addr1 = new AddressDTO();
        $addr1->street = '123 Main St';
        $addr1->city = 'New York';
        $addr1->zipCode = '10001';

        $addr2 = new AddressDTO();
        $addr2->street = '456 Oak Ave';
        $addr2->city = 'Los Angeles';
        $addr2->zipCode = '90001';

        $dto->locationDTOs = [$addr1, $addr2];

        // Map to Company
        $company = $this->mapper->map($dto, Company::class);

        $this->assertCount(2, $company->locations);
        $this->assertInstanceOf(Address::class, $company->locations[0]);
        $this->assertSame('123 Main St', $company->locations[0]->street);
        $this->assertSame('New York', $company->locations[0]->city);
        $this->assertSame('456 Oak Ave', $company->locations[1]->street);
        $this->assertSame('Los Angeles', $company->locations[1]->city);
    }
    public function testReverseNestedArrayMapping(): void
    {
        $profile = new Profile();
        $profile->avatar = 'Global Corp';
        $profile->bio = 'Leading multinational company';

        $user =  new User();
        $user->name = 'Alice';
        $user->email = 'email@email.com';
        $user->profile = $profile;

        // Map to Company
        $userDto = $this->mapper->map($user, UserDTO::class);

        $this->assertSame('Alice', $userDto->fullName);
        $this->assertSame('email@email.com', $userDto->email);
        $this->assertSame('Leading multinational company', $userDto->biography);
    }

    public function testMultipleArrayPropertiesMapping(): void
    {
        // Create company with both employees and locations
        $dto = new CompanyDTO();
        $dto->name = 'Multi Corp';

        $emp = new UserDTO();!
        $emp->email = 'employee@multi.com';
        $emp->fullName = 'Employee Name';
        $dto->employeeDTOs = [$emp];

        $addr = new AddressDTO();
        $addr->street = '789 Elm St';
        $addr->city = 'Chicago';
        $addr->zipCode = '60601';
        $dto->locationDTOs = [$addr];

        // Map to Company
        $company = $this->mapper->map($dto, Company::class);

        $this->assertCount(1, $company->employees);
        $this->assertCount(1, $company->locations);
        $this->assertInstanceOf(User::class, $company->employees[0]);
        $this->assertInstanceOf(Address::class, $company->locations[0]);
        $this->assertSame('employee@multi.com', $company->employees[0]->email);
        $this->assertSame('789 Elm St', $company->locations[0]->street);
    }

    public function testArrayMappingPreservesKeys(): void
    {
        $dto = new CompanyDTO();
        $dto->name = 'Key Corp';

        $emp1 = new UserDTO();
        $emp1->email = 'first@key.com';
        $emp1->fullName = 'First Employee';

        $emp2 = new UserDTO();
        $emp2->email = 'second@key.com';
        $emp2->fullName = 'Second Employee';

        // Use associative array keys
        $dto->employeeDTOs = ['manager' => $emp1, 'developer' => $emp2];

        $company = $this->mapper->map($dto, Company::class);

        $this->assertCount(2, $company->employees);
        $this->assertArrayHasKey('manager', $company->employees);
        $this->assertArrayHasKey('developer', $company->employees);
        $this->assertSame('first@key.com', $company->employees['manager']->email);
        $this->assertSame('second@key.com', $company->employees['developer']->email);
    }

    public function testArrayMappingWithInvalidItemTypeThrowsException(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Array item at index 0');
        $this->expectExceptionMessage('is not an object');

        $dto = new CompanyDTO();
        $dto->name = 'Invalid Corp';
        $dto->employeeDTOs = ['not-an-object']; // String instead of object

        $this->mapper->map($dto, Company::class);
    }

    public function testLargeArrayMapping(): void
    {
        $dto = new CompanyDTO();
        $dto->name = 'Large Corp';

        // Create 100 employees
        for ($i = 0; $i < 100; $i++) {
            $emp = new UserDTO();
            $emp->email = "employee{$i}@large.com";
            $emp->fullName = "Employee {$i}";
            $emp->age = 20 + ($i % 50);
            $dto->employeeDTOs[] = $emp;
        }

        $company = $this->mapper->map($dto, Company::class);

        $this->assertCount(100, $company->employees);
        $this->assertSame('employee0@large.com', $company->employees[0]->email);
        $this->assertSame('employee99@large.com', $company->employees[99]->email);
    }

    public function testArrayObjectMapping(): void
    {
        $dto = new CompanyWithArrayObjectDTO();
        $dto->name = 'ArrayObject Corp';

        $emp1 = new UserDTO();
        $emp1->email = 'ao1@test.com';
        $emp1->fullName = 'AO Employee 1';

        $emp2 = new UserDTO();
        $emp2->email = 'ao2@test.com';
        $emp2->fullName = 'AO Employee 2';

        // Add items to ArrayObject
        $dto->employeeDTOs->append($emp1);
        $dto->employeeDTOs->append($emp2);

        // Map to Company entity
        $company = $this->mapper->map($dto, CompanyWithArrayObject::class);

        $this->assertInstanceOf(\ArrayObject::class, $company->employees);
        $this->assertCount(2, $company->employees);
        $this->assertInstanceOf(User::class, $company->employees[0]);
        $this->assertSame('ao1@test.com', $company->employees[0]->email);
        $this->assertSame('AO Employee 1', $company->employees[0]->name);

        // Test bidirectional mapping
        $dtoBack = $this->mapper->map($company, CompanyWithArrayObjectDTO::class);
        $this->assertInstanceOf(\ArrayObject::class, $dtoBack->employeeDTOs);
        $this->assertCount(2, $dtoBack->employeeDTOs);
        $this->assertSame('ao1@test.com', $dtoBack->employeeDTOs[0]->email);
    }
}
