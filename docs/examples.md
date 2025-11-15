# Advanced Examples

This document provides real-world examples and advanced use cases for Simmap.

## Table of Contents

- [E-commerce Product Management](#e-commerce-product-management)
- [User Profile Management](#user-profile-management)
- [API Request/Response Mapping](#api-requestresponse-mapping)
- [Form Handling](#form-handling)
- [Multi-Level Nesting](#multi-level-nesting)
- [Partial Updates](#partial-updates)
- [Collection Mapping](#collection-mapping)
- [Integration Patterns](#integration-patterns)

## E-commerce Product Management

### Scenario: Map API input to product entity

```php
use Alecszaharia\Simmap\Attribute\MapTo;
use Alecszaharia\Simmap\Attribute\Ignore;
use Alecszaharia\Simmap\Mapper;

// API Input DTO
class CreateProductRequest
{
    public string $name;
    public string $description;

    #[MapTo('price.amount')]
    public float $priceAmount;

    #[MapTo('price.currency')]
    public string $priceCurrency;

    #[MapTo('category.name')]
    public string $categoryName;

    #[MapTo('inventory.stock')]
    public int $stockQuantity;

    #[MapTo('inventory.warehouse')]
    public string $warehouseLocation;

    #[Ignore]
    public ?string $requestId = null; // API metadata, don't persist
}

// Domain Entities
class Product
{
    public string $name;
    public string $description;
    public Price $price;
    public Category $category;
    public Inventory $inventory;

    public function __construct()
    {
        $this->price = new Price();
        $this->category = new Category();
        $this->inventory = new Inventory();
    }
}

class Price
{
    public float $amount;
    public string $currency = 'USD';
}

class Category
{
    public string $name;
}

class Inventory
{
    public int $stock;
    public string $warehouse;
}

// Usage in controller
class ProductController
{
    public function __construct(
        private Mapper $mapper,
        private EntityManagerInterface $em
    ) {}

    #[Route('/api/products', methods: ['POST'])]
    public function create(CreateProductRequest $request): Response
    {
        // Map flat DTO to nested entity structure
        $product = $this->mapper->map($request, Product::class);

        $this->em->persist($product);
        $this->em->flush();

        return new JsonResponse(['id' => $product->id], 201);
    }
}
```

### Reverse: Entity to API Response

```php
class ProductResponse
{
    public string $name;
    public string $description;

    #[MapTo('price.amount')]
    public float $priceAmount;

    #[MapTo('price.currency')]
    public string $priceCurrency;

    #[MapTo('category.name')]
    public string $categoryName;

    #[MapTo('inventory.stock')]
    public int $stockQuantity;
}

// Map entity to response DTO
$responseDto = $mapper->map($product, ProductResponse::class);
return new JsonResponse($responseDto);
```

## User Profile Management

### Complex nested user structure

```php
class UserProfileDTO
{
    // Basic info
    public string $firstName;
    public string $lastName;
    public string $email;

    // Address
    #[MapTo('contactInfo.address.street')]
    public string $street;

    #[MapTo('contactInfo.address.city')]
    public string $city;

    #[MapTo('contactInfo.address.zipCode')]
    public string $zipCode;

    #[MapTo('contactInfo.address.country')]
    public string $country;

    // Phone
    #[MapTo('contactInfo.phone.number')]
    public string $phoneNumber;

    #[MapTo('contactInfo.phone.type')]
    public string $phoneType;

    // Preferences
    #[MapTo('preferences.newsletter')]
    public bool $wantsNewsletter;

    #[MapTo('preferences.language')]
    public string $preferredLanguage;

    #[MapTo('preferences.theme')]
    public string $theme;
}

class User
{
    public string $firstName;
    public string $lastName;
    public string $email;
    public ContactInfo $contactInfo;
    public UserPreferences $preferences;

    public function __construct()
    {
        $this->contactInfo = new ContactInfo();
        $this->preferences = new UserPreferences();
    }
}

class ContactInfo
{
    public Address $address;
    public Phone $phone;

    public function __construct()
    {
        $this->address = new Address();
        $this->phone = new Phone();
    }
}

class Address
{
    public string $street;
    public string $city;
    public string $zipCode;
    public string $country;
}

class Phone
{
    public string $number;
    public string $type = 'mobile';
}

class UserPreferences
{
    public bool $newsletter = false;
    public string $language = 'en';
    public string $theme = 'light';
}

// Map flat DTO to deeply nested structure
$user = $mapper->map($profileDto, User::class);
```

## API Request/Response Mapping

### RESTful API with different request/response shapes

```php
// POST /api/orders - Create order
class CreateOrderRequest
{
    public int $customerId;

    #[MapTo('shippingAddress.street')]
    public string $shippingStreet;

    #[MapTo('shippingAddress.city')]
    public string $shippingCity;

    #[Ignore]
    public array $items = []; // Handle separately
}

// GET /api/orders/{id} - Order response
class OrderResponse
{
    public int $id;
    public int $customerId;
    public string $status;

    #[MapTo('shippingAddress.street')]
    public string $shippingStreet;

    #[MapTo('shippingAddress.city')]
    public string $shippingCity;

    #[MapTo('createdAt')]
    public string $orderDate; // Different name in response

    #[Ignore]
    public array $items = []; // Populated separately
}

class Order
{
    public int $id;
    public int $customerId;
    public string $status = 'pending';
    public Address $shippingAddress;
    public \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->shippingAddress = new Address();
        $this->createdAt = new \DateTime();
    }
}

// Controller usage
class OrderController
{
    public function create(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->mapper->map($request, Order::class);

        // Handle items separately (collection mapping)
        foreach ($request->items as $itemData) {
            $order->addItem(/* ... */);
        }

        $this->em->persist($order);
        $this->em->flush();

        // Map to response
        $response = $this->mapper->map($order, OrderResponse::class);
        $response->items = $order->getItems(); // Add items

        return new JsonResponse($response, 201);
    }
}
```

## Form Handling

### Symfony Form to Entity mapping

```php
class RegistrationFormData
{
    public string $username;
    public string $email;
    public string $plainPassword;

    #[MapTo('profile.firstName')]
    public string $firstName;

    #[MapTo('profile.lastName')]
    public string $lastName;

    #[MapTo('profile.birthDate')]
    public ?\DateTimeInterface $birthDate = null;

    public bool $agreeToTerms = false;
}

class User
{
    public string $username;
    public string $email;
    public string $password; // Will be hashed
    public UserProfile $profile;
    public \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->profile = new UserProfile();
        $this->createdAt = new \DateTime();
    }
}

class UserProfile
{
    public string $firstName;
    public string $lastName;
    public ?\DateTimeInterface $birthDate = null;
}

// In controller
class RegistrationController
{
    public function register(Request $request): Response
    {
        $formData = new RegistrationFormData();
        $form = $this->createForm(RegistrationFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Map form data to user entity
            $user = $this->mapper->map($formData, User::class);

            // Hash password (not mapped directly)
            $user->password = $this->passwordHasher->hashPassword(
                $user,
                $formData->plainPassword
            );

            $this->em->persist($user);
            $this->em->flush();

            return $this->redirectToRoute('login');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
```

## Multi-Level Nesting

### Enterprise organization structure

```php
class OrganizationDTO
{
    public string $name;

    // Department -> Team -> Manager
    #[MapTo('department.name')]
    public string $departmentName;

    #[MapTo('department.team.name')]
    public string $teamName;

    #[MapTo('department.team.manager.firstName')]
    public string $managerFirstName;

    #[MapTo('department.team.manager.lastName')]
    public string $managerLastName;

    #[MapTo('department.team.manager.email')]
    public string $managerEmail;

    // Location
    #[MapTo('location.office.building')]
    public string $building;

    #[MapTo('location.office.floor')]
    public int $floor;

    #[MapTo('location.office.room')]
    public string $room;
}

class Organization
{
    public string $name;
    public Department $department;
    public Location $location;

    public function __construct()
    {
        $this->department = new Department();
        $this->location = new Location();
    }
}

class Department
{
    public string $name;
    public Team $team;

    public function __construct()
    {
        $this->team = new Team();
    }
}

class Team
{
    public string $name;
    public Manager $manager;

    public function __construct()
    {
        $this->manager = new Manager();
    }
}

class Manager
{
    public string $firstName;
    public string $lastName;
    public string $email;
}

class Location
{
    public Office $office;

    public function __construct()
    {
        $this->office = new Office();
    }
}

class Office
{
    public string $building;
    public int $floor;
    public string $room;
}

// Map flat DTO to 4-level nested structure
$org = $mapper->map($dto, Organization::class);
```

## Partial Updates

### Update only specific fields

```php
class UpdateUserRequest
{
    public ?string $email = null;
    public ?string $firstName = null;
    public ?string $lastName = null;

    #[MapTo('profile.bio')]
    public ?string $bio = null;
}

class UserUpdateService
{
    public function update(int $userId, UpdateUserRequest $request): User
    {
        $user = $this->userRepository->find($userId);

        if (!$user) {
            throw new NotFoundHttpException();
        }

        // Map non-null values to existing user
        $this->mapNonNullValues($request, $user);

        $this->em->flush();

        return $user;
    }

    private function mapNonNullValues(object $source, object $target): void
    {
        // Create temporary object with all mappings
        $temp = $this->mapper->map($source, get_class($target));

        // Copy only non-null values
        $reflection = new \ReflectionClass($temp);
        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($temp);
            if ($value !== null) {
                $property->setValue($target, $value);
            }
        }
    }
}
```

## Collection Mapping

### Mapping arrays of objects

```php
class OrderDTO
{
    public int $customerId;

    #[Ignore]
    public array $itemDTOs = [];
}

class OrderItemDTO
{
    public int $productId;
    public int $quantity;
    public float $price;
}

class OrderService
{
    public function createOrder(OrderDTO $orderDto): Order
    {
        // Map main order
        $order = $this->mapper->map($orderDto, Order::class);

        // Map collection items
        foreach ($orderDto->itemDTOs as $itemDto) {
            $item = $this->mapper->map($itemDto, OrderItem::class);
            $order->addItem($item);
        }

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
```

### Bulk mapping helper

```php
class MappingHelper
{
    public function __construct(private Mapper $mapper) {}

    /**
     * Maps array of objects to target class
     *
     * @template T
     * @param array<object> $sources
     * @param class-string<T> $targetClass
     * @return array<T>
     */
    public function mapCollection(array $sources, string $targetClass): array
    {
        return array_map(
            fn($source) => $this->mapper->map($source, $targetClass),
            $sources
        );
    }
}

// Usage
$orderItems = $helper->mapCollection($itemDTOs, OrderItem::class);
```

## Integration Patterns

### Repository Pattern

```php
class UserRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private Mapper $mapper
    ) {}

    public function create(CreateUserDTO $dto): User
    {
        $user = $this->mapper->map($dto, User::class);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    public function update(User $user, UpdateUserDTO $dto): User
    {
        $this->mapper->map($dto, $user);
        $this->em->flush();
        return $user;
    }

    public function toDTO(User $user): UserDTO
    {
        return $this->mapper->map($user, UserDTO::class);
    }
}
```

### Service Layer

```php
class UserService
{
    public function __construct(
        private UserRepository $repository,
        private Mapper $mapper,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function registerUser(RegistrationDTO $dto): UserDTO
    {
        // Validate
        $this->validator->validate($dto);

        // Map to entity
        $user = $this->mapper->map($dto, User::class);

        // Business logic
        $user->activate();
        $user->assignDefaultRole();

        // Persist
        $this->repository->save($user);

        // Dispatch event
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user));

        // Map to response DTO
        return $this->mapper->map($user, UserDTO::class);
    }
}
```

### Event Subscriber Integration

```php
class UserUpdatedSubscriber implements EventSubscriberInterface
{
    public function __construct(private Mapper $mapper) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UserUpdatedEvent::class => 'onUserUpdated',
        ];
    }

    public function onUserUpdated(UserUpdatedEvent $event): void
    {
        $user = $event->getUser();

        // Map to cache DTO
        $cacheDto = $this->mapper->map($user, UserCacheDTO::class);

        // Update cache
        $this->cache->set('user_' . $user->getId(), $cacheDto);

        // Map to search index DTO
        $searchDto = $this->mapper->map($user, UserSearchDTO::class);

        // Update search index
        $this->searchEngine->index($searchDto);
    }
}
```

### Command/Query Separation (CQRS)

```php
// Command side - write model
class CreateProductCommand
{
    public function __construct(
        public string $name,
        public float $price,
        public int $stock
    ) {}
}

class CreateProductHandler
{
    public function __construct(private Mapper $mapper) {}

    public function handle(CreateProductCommand $command): int
    {
        $product = $this->mapper->map($command, Product::class);
        $this->em->persist($product);
        $this->em->flush();

        return $product->id;
    }
}

// Query side - read model
class ProductListQuery
{
    public function __construct(
        private Mapper $mapper,
        private ProductRepository $repository
    ) {}

    public function execute(): array
    {
        $products = $this->repository->findAll();

        // Map entities to read DTOs
        return array_map(
            fn($product) => $this->mapper->map($product, ProductListItemDTO::class),
            $products
        );
    }
}
```

## Best Practices from Examples

1. **Always initialize nested objects** in constructors
2. **Use #[Ignore] for metadata** that shouldn't be persisted
3. **Handle collections separately** - don't try to map arrays directly
4. **Create helper methods** for common patterns (bulk mapping, non-null mapping)
5. **Separate request/response DTOs** even if similar structure
6. **Map at service boundaries** (controller → service → repository)
7. **Use mapper in both directions** for symmetry
8. **Keep DTOs flat**, entities nested
9. **Validate before mapping** for security
10. **Map to existing instances** for updates

## Performance Tips from Real Usage

- Warm up cache at boot with frequently used mappings
- Reuse mapper instance across requests (singleton/service)
- Profile if mapping > 1000 objects per request
- Consider manual mapping for hot paths if needed
- Batch database flushes when mapping collections

See [performance.md](performance.md) for detailed optimization guide.
