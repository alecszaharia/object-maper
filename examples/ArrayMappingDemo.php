<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Alecszaharia\Simmap\Attribute\MapArray;
use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;
use Alecszaharia\Simmap\Mapper;

// Define DTOs and Entities
#[Mappable]
class OrderItemDTO
{
    public string $productName;
    public int $quantity;
    public float $price;
}

#[Mappable]
class OrderItem
{
    public string $productName;
    public int $quantity;
    public float $price;
}

#[Mappable]
class OrderDTO
{
    public int $orderId;
    public string $customerName;

    #[MapArray(OrderItem::class)]
    public array $items = [];
}

#[Mappable]
class Order
{
    public int $orderId;
    public string $customerName;

    #[MapArray(OrderItemDTO::class)]
    public array $items = [];
}

// Create sample data
$item1 = new OrderItemDTO();
$item1->productName = 'Laptop';
$item1->quantity = 1;
$item1->price = 999.99;

$item2 = new OrderItemDTO();
$item2->productName = 'Mouse';
$item2->quantity = 2;
$item2->price = 29.99;

$item3 = new OrderItemDTO();
$item3->productName = 'Keyboard';
$item3->quantity = 1;
$item3->price = 79.99;

$orderDto = new OrderDTO();
$orderDto->orderId = 12345;
$orderDto->customerName = 'John Doe';
$orderDto->items = [$item1, $item2, $item3];

// Create mapper and map
$mapper = new Mapper();

echo "=== Array Mapping Demo ===\n\n";

echo "Source DTO:\n";
echo "Order ID: {$orderDto->orderId}\n";
echo "Customer: {$orderDto->customerName}\n";
echo "Items count: " . count($orderDto->items) . "\n";
foreach ($orderDto->items as $index => $item) {
    echo "  Item " . ($index + 1) . ": {$item->productName} - Qty: {$item->quantity}, Price: \${$item->price}\n";
}

echo "\n--- Mapping DTO → Entity ---\n\n";

$order = $mapper->map($orderDto, Order::class);

echo "Mapped Entity:\n";
echo "Order ID: {$order->orderId}\n";
echo "Customer: {$order->customerName}\n";
echo "Items count: " . count($order->items) . "\n";
foreach ($order->items as $index => $item) {
    echo "  Item " . ($index + 1) . ": {$item->productName} - Qty: {$item->quantity}, Price: \${$item->price}\n";
    echo "    Type: " . get_class($item) . "\n";
}

echo "\n--- Symmetrical Mapping: Entity → DTO ---\n\n";

$reversedDto = $mapper->map($order, OrderDTO::class);

echo "Reversed DTO:\n";
echo "Order ID: {$reversedDto->orderId}\n";
echo "Customer: {$reversedDto->customerName}\n";
echo "Items count: " . count($reversedDto->items) . "\n";
foreach ($reversedDto->items as $index => $item) {
    echo "  Item " . ($index + 1) . ": {$item->productName} - Qty: {$item->quantity}, Price: \${$item->price}\n";
    echo "    Type: " . get_class($item) . "\n";
}

echo "\n=== Demo with Associative Arrays ===\n\n";

// Using associative array with string keys
$assocDto = new OrderDTO();
$assocDto->orderId = 67890;
$assocDto->customerName = 'Jane Smith';
$assocDto->items = [
    'laptop' => $item1,
    'mouse' => $item2,
    'keyboard' => $item3
];

$assocOrder = $mapper->map($assocDto, Order::class);

echo "Associative array keys preserved:\n";
foreach ($assocOrder->items as $key => $item) {
    echo "  [{$key}]: {$item->productName}\n";
}

echo "\n✓ Array mapping implementation complete!\n";
