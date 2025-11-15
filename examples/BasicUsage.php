<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Alecszaharia\Simmap\Attribute\Ignore;
use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;
use Alecszaharia\Simmap\Mapper;

// Example 1: Simple DTO to Entity mapping with auto-mapping
#[Mappable]
class UserDTO
{
    public string $name;
    public string $email;
    public int $age;
}

#[Mappable]
class UserEntity
{
    public string $name;
    public string $email;
    public int $age;
}

$dto = new UserDTO();
$dto->name = 'John Doe';
$dto->email = 'john@example.com';
$dto->age = 30;

$mapper = new Mapper();

// Map DTO to Entity (auto-mapping - properties have same names)
$entity = $mapper->map($dto, UserEntity::class);
echo "Example 1 - Auto-mapping:\n";
echo "Entity name: {$entity->name}\n";
echo "Entity email: {$entity->email}\n";
echo "Entity age: {$entity->age}\n\n";

// Example 2: Custom property mapping with #[MapTo] attribute
#[Mappable]
class ProductDTO
{
    public string $productName;
    public float $price;

    #[MapTo('quantity')]
    public int $stock;
}

#[Mappable]
class ProductEntity
{
    public string $productName;
    public float $price;
    public int $quantity;
}

$productDto = new ProductDTO();
$productDto->productName = 'Laptop';
$productDto->price = 999.99;
$productDto->stock = 50;

$productEntity = $mapper->map($productDto, ProductEntity::class);
echo "Example 2 - Custom mapping:\n";
echo "Product: {$productEntity->productName}\n";
echo "Price: {$productEntity->price}\n";
echo "Quantity (mapped from stock): {$productEntity->quantity}\n\n";

// Example 3: Symmetrical mapping (reverse direction)
$productDto2 = $mapper->map($productEntity, ProductDTO::class);
echo "Example 3 - Symmetrical mapping:\n";
echo "Stock (mapped from quantity): {$productDto2->stock}\n\n";

// Example 4: Nested property mapping
#[Mappable]
class Address
{
    public string $city;
    public string $country;
}

#[Mappable]
class PersonDTO
{
    public string $name;

    #[MapTo('address.city')]
    public string $city;

    #[MapTo('address.country')]
    public string $country;
}

#[Mappable]
class PersonEntity
{
    public string $name;
    public Address $address;

    public function __construct()
    {
        $this->address = new Address();
    }
}

$personDto = new PersonDTO();
$personDto->name = 'Jane Smith';
$personDto->city = 'New York';
$personDto->country = 'USA';

$personEntity = $mapper->map($personDto, PersonEntity::class);
echo "Example 4 - Nested property mapping:\n";
echo "Person: {$personEntity->name}\n";
echo "City: {$personEntity->address->city}\n";
echo "Country: {$personEntity->address->country}\n\n";

// Example 5: Using #[Ignore] attribute
#[Mappable]
class OrderDTO
{
    public int $orderId;
    public float $total;

    #[Ignore]
    public string $tempData; // This won't be mapped
}

#[Mappable]
class OrderEntity
{
    public int $orderId;
    public float $total;
    public string $tempData = 'default';
}

$orderDto = new OrderDTO();
$orderDto->orderId = 12345;
$orderDto->total = 299.99;
$orderDto->tempData = 'ignored value';

$orderEntity = $mapper->map($orderDto, OrderEntity::class);
echo "Example 5 - Ignore attribute:\n";
echo "Order ID: {$orderEntity->orderId}\n";
echo "Total: {$orderEntity->total}\n";
echo "Temp data (should be 'default'): {$orderEntity->tempData}\n\n";

// Example 6: Array mapping with #[MapArray] attribute
use Alecszaharia\Simmap\Attribute\MapArray;

#[Mappable]
class CartItemDTO
{
    public string $productName;
    public int $quantity;
    public float $price;
}

#[Mappable]
class CartItem
{
    public string $productName;
    public int $quantity;
    public float $price;
}

#[Mappable]
class ShoppingCartDTO
{
    public int $cartId;

    #[MapArray(CartItem::class)]
    public array $items = [];
}

#[Mappable]
class ShoppingCart
{
    public int $cartId;

    #[MapArray(CartItemDTO::class)]
    public array $items = [];
}

$item1 = new CartItemDTO();
$item1->productName = 'Laptop';
$item1->quantity = 1;
$item1->price = 999.99;

$item2 = new CartItemDTO();
$item2->productName = 'Mouse';
$item2->quantity = 2;
$item2->price = 29.99;

$cartDto = new ShoppingCartDTO();
$cartDto->cartId = 1001;
$cartDto->items = [$item1, $item2];

$cart = $mapper->map($cartDto, ShoppingCart::class);
echo "Example 6 - Array mapping:\n";
echo "Cart ID: {$cart->cartId}\n";
echo "Number of items: " . count($cart->items) . "\n";
echo "First item: {$cart->items[0]->productName} (qty: {$cart->items[0]->quantity}, price: \${$cart->items[0]->price})\n";
echo "Second item: {$cart->items[1]->productName} (qty: {$cart->items[1]->quantity}, price: \${$cart->items[1]->price})\n\n";

// Symmetrical array mapping works too!
$cartDto2 = $mapper->map($cart, ShoppingCartDTO::class);
echo "Symmetrical array mapping - items count: " . count($cartDto2->items) . "\n";
