<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Unit;

use Alecszaharia\Simmap\Attribute\MapArray;
use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Attribute\MapTo;
use Alecszaharia\Simmap\Mapper;
use PHPUnit\Framework\TestCase;

class ArrayMappingTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new Mapper();
    }

    public function testMapArrayWithSimpleObjects(): void
    {
        $source = new #[Mappable] class {
            #[MapArray(ArrayMappingTargetItem::class)]
            public array $items;

            public function __construct()
            {
                $item1 = new ArrayMappingSourceItem();
                $item1->name = 'Item 1';
                $item1->value = 100;

                $item2 = new ArrayMappingSourceItem();
                $item2->name = 'Item 2';
                $item2->value = 200;

                $this->items = [$item1, $item2];
            }
        };

        $target = new #[Mappable] class {
            #[MapArray(ArrayMappingSourceItem::class)]
            public array $items = [];
        };

        $result = $this->mapper->map($source, $target);

        $this->assertCount(2, $result->items);
        $this->assertInstanceOf(ArrayMappingTargetItem::class, $result->items[0]);
        $this->assertInstanceOf(ArrayMappingTargetItem::class, $result->items[1]);
        $this->assertSame('Item 1', $result->items[0]->name);
        $this->assertSame(100, $result->items[0]->value);
        $this->assertSame('Item 2', $result->items[1]->name);
        $this->assertSame(200, $result->items[1]->value);

        // Test empty array edge case
        $emptySource = new #[Mappable] class {
            #[MapArray(ArrayMappingTargetItem::class)]
            public array $items = [];
        };
        $emptyResult = $this->mapper->map($emptySource, $target);
        $this->assertIsArray($emptyResult->items);
        $this->assertEmpty($emptyResult->items);
    }

    public function testMapArrayPreservesKeys(): void
    {
        $source = new #[Mappable] class {
            #[MapArray(ArrayMappingTargetItem::class)]
            public array $items;

            public function __construct()
            {
                $item1 = new ArrayMappingSourceItem();
                $item1->name = 'First';
                $item1->value = 1;

                $item2 = new ArrayMappingSourceItem();
                $item2->name = 'Second';
                $item2->value = 2;

                $this->items = ['first' => $item1, 'second' => $item2];
            }
        };

        $target = new #[Mappable] class {
            #[MapArray(ArrayMappingSourceItem::class)]
            public array $items = [];
        };

        $result = $this->mapper->map($source, $target);

        $this->assertArrayHasKey('first', $result->items);
        $this->assertArrayHasKey('second', $result->items);
        $this->assertSame('First', $result->items['first']->name);
        $this->assertSame('Second', $result->items['second']->name);
    }

    public function testMapArrayWithMapToAttribute(): void
    {
        $source = new #[Mappable] class {
            #[MapArray(ArrayMappingTargetItem::class)]
            #[MapTo('targetItems')]
            public array $sourceItems;

            public function __construct()
            {
                $item = new ArrayMappingSourceItem();
                $item->name = 'Test';
                $item->value = 42;

                $this->sourceItems = [$item];
            }
        };

        $target = new #[Mappable] class {
            #[MapArray(ArrayMappingSourceItem::class)]
            public array $targetItems = [];
        };

        $result = $this->mapper->map($source, $target);

        $this->assertCount(1, $result->targetItems);
        $this->assertSame('Test', $result->targetItems[0]->name);
    }

    public function testSymmetricalArrayMapping(): void
    {
        $sourceItem = new ArrayMappingSourceItem();
        $sourceItem->name = 'Original';
        $sourceItem->value = 999;

        $source = new #[Mappable] class {
            #[MapArray(ArrayMappingTargetItem::class)]
            public array $items;

            public function __construct()
            {
                $this->items = [];
            }
        };
        $source->items = [$sourceItem];

        $target = new #[Mappable] class {
            #[MapArray(ArrayMappingSourceItem::class)]
            public array $items = [];
        };

        // Forward mapping
        $result = $this->mapper->map($source, $target);
        $this->assertInstanceOf(ArrayMappingTargetItem::class, $result->items[0]);
        $this->assertSame('Original', $result->items[0]->name);

        // Reverse mapping
        $reversed = $this->mapper->map($result, get_class($source));
        $this->assertInstanceOf(ArrayMappingSourceItem::class, $reversed->items[0]);
        $this->assertSame('Original', $reversed->items[0]->name);
    }

    public function testMapArrayPreservesNonObjectValues(): void
    {
        $source = new #[Mappable] class {
            #[MapArray(ArrayMappingTargetItem::class)]
            public array $items;

            public function __construct()
            {
                $item = new ArrayMappingSourceItem();
                $item->name = 'Object';
                $item->value = 1;

                $this->items = [$item, 'string', 123, null];
            }
        };

        $target = new #[Mappable] class {
            #[MapArray(ArrayMappingSourceItem::class)]
            public array $items = [];
        };

        $result = $this->mapper->map($source, $target);

        $this->assertCount(4, $result->items);
        $this->assertInstanceOf(ArrayMappingTargetItem::class, $result->items[0]);
        $this->assertSame('string', $result->items[1]);
        $this->assertSame(123, $result->items[2]);
        $this->assertNull($result->items[3]);
    }

    public function testMapArrayWithMultipleArrays(): void
    {
        $item1 = new ArrayMappingSourceItem();
        $item1->name = 'Item 1';
        $item1->value = 1;

        $item2 = new ArrayMappingSourceItem();
        $item2->name = 'Item 2';
        $item2->value = 2;

        $source = new #[Mappable] class {
            #[MapArray(ArrayMappingTargetItem::class)]
            public array $firstArray;

            #[MapArray(ArrayMappingTargetItem::class)]
            public array $secondArray;

            public function __construct()
            {
                $this->firstArray = [];
                $this->secondArray = [];
            }
        };

        $source->firstArray = [$item1];
        $source->secondArray = [$item2];

        $target = new #[Mappable] class {
            #[MapArray(ArrayMappingSourceItem::class)]
            public array $firstArray = [];

            #[MapArray(ArrayMappingSourceItem::class)]
            public array $secondArray = [];
        };

        $result = $this->mapper->map($source, $target);

        $this->assertCount(1, $result->firstArray);
        $this->assertCount(1, $result->secondArray);
        $this->assertSame('Item 1', $result->firstArray[0]->name);
        $this->assertSame('Item 2', $result->secondArray[0]->name);
    }
}

// Test helper classes
#[Mappable]
class ArrayMappingSourceItem
{
    public string $name = '';
    public int $value = 0;
}

#[Mappable]
class ArrayMappingTargetItem
{
    public string $name = '';
    public int $value = 0;
}
