# Advanced Examples

This document provides real-world examples and advanced use cases for Simmap.

## Important Note

**All classes that participate in mapping must be marked with the `#[Mappable]` attribute.** This is a required security feature that provides explicit opt-in control. All examples in this document already include this attribute.

```php
use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable]  // ← Required on both source and target
class YourClass {
    // ...
}
```

Without `#[Mappable]`, attempting to map will throw:
```
MappingException: Class "YourClass" cannot be used as source for mapping.
Add #[Mappable] attribute to the class to enable mapping.
```

## Table of Contents

- [E-commerce Product Management](#e-commerce-product-management)
- [User Profile Management](#user-profile-management)
- [API Request/Response Mapping](#api-requestresponse-mapping)
- [Form Handling](#form-handling)
- [Multi-Level Nesting](#multi-level-nesting)
- [Partial Updates](#partial-updates)
- [Array Mapping with #[MapArray]](#array-mapping-with-maparray)
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

## Array Mapping with #[MapArray]

The `#[MapArray]` attribute enables automatic mapping of array elements from one type to another. This is perfect for scenarios where you need to convert collections of objects, such as order items, comments, tags, or any other one-to-many relationships.

### Basic Array Mapping

```php
use Alecszaharia\Simmap\Attribute\MapArray;
use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Mapper;

// Source classes
#[Mappable]
class ProductDTO
{
    public string $name;
    public float $price;
}

#[Mappable]
class OrderDTO
{
    public int $customerId;

    #[MapArray(OrderItemEntity::class)]
    public array $items = [];
}

// Target classes
#[Mappable]
class OrderItemEntity
{
    public string $name;
    public float $price;
}

#[Mappable]
class OrderEntity
{
    public int $customerId;

    #[MapArray(ProductDTO::class)]
    public array $items = [];
}

// Usage
$orderDto = new OrderDTO();
$orderDto->customerId = 123;

$item1 = new ProductDTO();
$item1->name = 'Laptop';
$item1->price = 999.99;

$item2 = new ProductDTO();
$item2->name = 'Mouse';
$item2->price = 29.99;

$orderDto->items = [$item1, $item2];

$mapper = new Mapper();

// Automatically maps each array element
$orderEntity = $mapper->map($orderDto, OrderEntity::class);
// Result: $orderEntity->items contains OrderItemEntity instances
```

### E-commerce: Order with Line Items

```php
use Alecszaharia\Simmap\Attribute\MapArray;
use Alecszaharia\Simmap\Attribute\MapTo;
use Alecszaharia\Simmap\Attribute\Mappable;

// API Request DTO
#[Mappable]
class CreateOrderRequest
{
    public int $customerId;

    #[MapTo('shippingAddress.street')]
    public string $street;

    #[MapTo('shippingAddress.city')]
    public string $city;

    #[MapTo('shippingAddress.zipCode')]
    public string $zipCode;

    #[MapArray(OrderLineItem::class)]
    public array $lineItems = [];
}

#[Mappable]
class OrderLineItemDTO
{
    public string $productId;
    public int $quantity;
    public float $unitPrice;
    public ?string $notes = null;
}

// Domain Entities
#[Mappable]
class Order
{
    public int $customerId;
    public Address $shippingAddress;

    #[MapArray(OrderLineItemDTO::class)]
    public array $lineItems = [];

    public function __construct()
    {
        $this->shippingAddress = new Address();
    }
}

#[Mappable]
class OrderLineItem
{
    public string $productId;
    public int $quantity;
    public float $unitPrice;
    public ?string $notes = null;
}

#[Mappable]
class Address
{
    public string $street;
    public string $city;
    public string $zipCode;
}

// Controller
class OrderController
{
    public function __construct(private Mapper $mapper) {}

    public function create(CreateOrderRequest $request): JsonResponse
    {
        // Single map() call handles both nested objects AND array mapping
        $order = $this->mapper->map($request, Order::class);

        // All line items are automatically converted
        // $order->lineItems contains OrderLineItem instances

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $order->id], 201);
    }
}
```

### Blog Post with Comments

```php
#[Mappable]
class BlogPostDTO
{
    public string $title;
    public string $content;
    public string $authorId;

    #[MapArray(CommentEntity::class)]
    public array $comments = [];
}

#[Mappable]
class CommentDTO
{
    public string $author;
    public string $text;
    public \DateTimeInterface $createdAt;
}

#[Mappable]
class BlogPost
{
    public string $title;
    public string $content;
    public string $authorId;

    #[MapArray(CommentDTO::class)]
    public array $comments = [];
}

#[Mappable]
class CommentEntity
{
    public string $author;
    public string $text;
    public \DateTimeInterface $createdAt;
}

// Map blog post with all comments in one operation
$blogPost = $mapper->map($blogPostDto, BlogPost::class);
```

### Associative Arrays (Preserving Keys)

The `#[MapArray]` attribute preserves array keys, making it perfect for associative arrays:

```php
#[Mappable]
class ProductCatalogDTO
{
    #[MapArray(ProductEntity::class)]
    public array $products = []; // ['SKU-001' => Product, 'SKU-002' => Product]
}

#[Mappable]
class ProductDTO
{
    public string $name;
    public float $price;
    public int $stock;
}

#[Mappable]
class ProductCatalog
{
    #[MapArray(ProductDTO::class)]
    public array $products = [];
}

#[Mappable]
class ProductEntity
{
    public string $name;
    public float $price;
    public int $stock;
}

// Usage with associative array
$catalogDto = new ProductCatalogDTO();
$catalogDto->products = [
    'SKU-001' => $product1,
    'SKU-002' => $product2,
    'SKU-003' => $product3,
];

$catalog = $mapper->map($catalogDto, ProductCatalog::class);
// Keys are preserved: $catalog->products['SKU-001'] still exists
```

### Combining #[MapArray] with #[MapTo]

You can combine array mapping with property name changes:

```php
#[Mappable]
class ShoppingCartDTO
{
    public string $userId;

    #[MapArray(CartItemEntity::class)]
    #[MapTo('cartItems')]  // Rename property
    public array $items = [];
}

#[Mappable]
class CartItemDTO
{
    public string $productId;
    public int $quantity;
}

#[Mappable]
class ShoppingCart
{
    public string $userId;

    #[MapArray(CartItemDTO::class)]
    public array $cartItems = [];
}

#[Mappable]
class CartItemEntity
{
    public string $productId;
    public int $quantity;
}

// Maps items → cartItems AND converts each element
$cart = $mapper->map($cartDto, ShoppingCart::class);
```

### Symmetrical Array Mapping

Array mapping works bidirectionally with a single definition:

```php
#[Mappable]
class UserListRequest
{
    #[MapArray(UserEntity::class)]
    public array $users = [];
}

#[Mappable]
class UserDTO
{
    public string $name;
    public string $email;
}

#[Mappable]
class UserList
{
    #[MapArray(UserDTO::class)]
    public array $users = [];
}

#[Mappable]
class UserEntity
{
    public string $name;
    public string $email;
}

// Forward: DTO → Entity
$userList = $mapper->map($request, UserList::class);

// Reverse: Entity → DTO
$requestDto = $mapper->map($userList, UserListRequest::class);

// Both directions work automatically!
```

### Multiple Array Properties

You can have multiple array mappings in a single class:

```php
#[Mappable]
class DocumentDTO
{
    public string $title;

    #[MapArray(ImageEntity::class)]
    public array $images = [];

    #[MapArray(AttachmentEntity::class)]
    public array $attachments = [];

    #[MapArray(TagEntity::class)]
    public array $tags = [];
}

#[Mappable]
class ImageDTO
{
    public string $url;
    public string $caption;
}

#[Mappable]
class AttachmentDTO
{
    public string $filename;
    public string $mimeType;
    public int $size;
}

#[Mappable]
class TagDTO
{
    public string $name;
    public string $color;
}

#[Mappable]
class Document
{
    public string $title;

    #[MapArray(ImageDTO::class)]
    public array $images = [];

    #[MapArray(AttachmentDTO::class)]
    public array $attachments = [];

    #[MapArray(TagDTO::class)]
    public array $tags = [];
}

#[Mappable]
class ImageEntity
{
    public string $url;
    public string $caption;
}

#[Mappable]
class AttachmentEntity
{
    public string $filename;
    public string $mimeType;
    public int $size;
}

#[Mappable]
class TagEntity
{
    public string $name;
    public string $color;
}

// All three arrays are mapped automatically
$document = $mapper->map($documentDto, Document::class);
```

### Nested Objects with Arrays

Combine nested property mapping with array mapping:

```php
#[Mappable]
class InvoiceDTO
{
    public string $invoiceNumber;

    #[MapTo('customer.name')]
    public string $customerName;

    #[MapTo('customer.email')]
    public string $customerEmail;

    #[MapArray(InvoiceLineEntity::class)]
    public array $lineItems = [];
}

#[Mappable]
class InvoiceLineDTO
{
    public string $description;
    public float $amount;
    public int $quantity;
}

#[Mappable]
class Invoice
{
    public string $invoiceNumber;
    public Customer $customer;

    #[MapArray(InvoiceLineDTO::class)]
    public array $lineItems = [];

    public function __construct()
    {
        $this->customer = new Customer();
    }
}

#[Mappable]
class Customer
{
    public string $name;
    public string $email;
}

#[Mappable]
class InvoiceLineEntity
{
    public string $description;
    public float $amount;
    public int $quantity;
}

// Maps nested customer AND line items array
$invoice = $mapper->map($invoiceDto, Invoice::class);
```

### Handling Mixed Array Content

Arrays with non-object values are handled gracefully:

```php
#[Mappable]
class MixedContentDTO
{
    #[MapArray(ProcessedItem::class)]
    public array $data = [];
}

$dto = new MixedContentDTO();
$dto->data = [
    new RawItem(),        // Mapped to ProcessedItem
    'plain string',       // Preserved as-is
    42,                   // Preserved as-is
    null,                 // Preserved as-is
    ['nested' => 'array'] // Preserved as-is
];

$result = $mapper->map($dto, MixedContent::class);
// Result: Objects are mapped, scalars/nulls/arrays are preserved
```

### API Response Pagination

```php
#[Mappable]
class PaginatedResponse
{
    public int $page;
    public int $totalPages;
    public int $totalItems;

    #[MapArray(ProductEntity::class)]
    public array $data = [];
}

#[Mappable]
class ProductDTO
{
    public int $id;
    public string $name;
    public float $price;
    public bool $inStock;
}

#[Mappable]
class ProductList
{
    public int $page;
    public int $totalPages;
    public int $totalItems;

    #[MapArray(ProductDTO::class)]
    public array $data = [];
}

#[Mappable]
class ProductEntity
{
    public int $id;
    public string $name;
    public float $price;
    public bool $inStock;
}

// Perfect for API responses with collections
class ProductApiController
{
    public function list(int $page = 1): JsonResponse
    {
        $products = $this->productRepository->findPaginated($page);

        $response = new PaginatedResponse();
        $response->page = $page;
        $response->totalPages = $products->getTotalPages();
        $response->totalItems = $products->getTotalItems();
        $response->data = $products->getItems();

        // Convert all entities to DTOs for API response
        $dto = $this->mapper->map($response, ProductList::class);

        return new JsonResponse($dto);
    }
}
```

## Collection Mapping

Use the `#[MapArray]` attribute for automatic array mapping (see [Array Mapping with #[MapArray]](#array-mapping-with-maparray) section above).

```php
use Alecszaharia\Simmap\Attribute\MapArray;
use Alecszaharia\Simmap\Attribute\Mappable;

#[Mappable]
class OrderDTO
{
    public int $customerId;

    #[MapArray(OrderItem::class)]
    public array $items = [];
}

#[Mappable]
class OrderItemDTO
{
    public int $productId;
    public int $quantity;
    public float $price;
}

#[Mappable]
class Order
{
    public int $customerId;

    #[MapArray(OrderItemDTO::class)]
    public array $items = [];
}

#[Mappable]
class OrderItem
{
    public int $productId;
    public int $quantity;
    public float $price;
}

class OrderService
{
    public function createOrder(OrderDTO $orderDto): Order
    {
        // Single call - both order AND items are mapped automatically
        $order = $this->mapper->map($orderDto, Order::class);

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
```

### Bulk mapping helper (for advanced use cases)

If you need to map standalone arrays (not properties):

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

// Usage for standalone arrays
$productDTOs = [/* ... */];
$entities = $helper->mapCollection($productDTOs, ProductEntity::class);
```

**Note**: For array properties within objects, always prefer `#[MapArray]` over manual mapping.

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

1. **Always mark classes with #[Mappable]** - Required on both source and target classes
2. **Always initialize nested objects** in constructors
3. **Use #[Ignore] for metadata** that shouldn't be persisted
4. **Use #[MapArray] for array properties** - automatic element mapping with symmetry support
5. **Preserve array keys** - `#[MapArray]` maintains both indexed and associative keys
6. **Create helper methods** for common patterns (bulk mapping, non-null mapping)
7. **Separate request/response DTOs** even if similar structure
8. **Map at service boundaries** (controller → service → repository)
9. **Use mapper in both directions** for symmetry
10. **Keep DTOs flat**, entities nested (combine with `#[MapArray]` for collections)
11. **Validate before mapping** for security
12. **Map to existing instances** for updates
13. **Combine #[MapArray] with #[MapTo]** to both rename properties and map array elements

## Performance Tips from Real Usage

- Warm up cache at boot with frequently used mappings
- Reuse mapper instance across requests (singleton/service)
- Profile if mapping > 1000 objects per request
- Consider manual mapping for hot paths if needed
- Batch database flushes when mapping collections

See [performance.md](performance.md) for detailed optimization guide.
