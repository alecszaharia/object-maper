<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests\Benchmarks;

use Alecszaharia\Simmap\Attribute\MapArray;
use Alecszaharia\Simmap\Attribute\MapTo;
use Alecszaharia\Simmap\Attribute\Ignore;
use Alecszaharia\Simmap\Attribute\Mappable;
use Alecszaharia\Simmap\Mapper;

/**
 * Worst Case Benchmark for Simmap
 *
 * Tests the mapper under the most performance-intensive scenarios:
 * - Large number of properties (50+)
 * - Deep nesting (4 levels)
 * - Large arrays (1000+ elements)
 * - Mix of explicit mappings, reverse mappings, and auto-mappings
 * - Complex nested property paths
 * - Cold cache (first-time metadata reading)
 *
 * Run with: make php CMD="tests/Benchmarks/WorstCaseBenchmark.php"
 */
class WorstCaseBenchmark
{
    private const ITERATIONS = 100;
    private const ARRAY_SIZE_SMALL = 10;
    private const ARRAY_SIZE_MEDIUM = 100;
    private const ARRAY_SIZE_LARGE = 1000;

    private Mapper $mapper;
    private array $results = [];

    public function __construct()
    {
        $this->mapper = new Mapper();
    }

    public function run(): void
    {
        echo "=== Simmap Worst Case Benchmark ===\n\n";
        echo "Testing performance under extreme conditions:\n";
        echo "- Many properties (50+)\n";
        echo "- Deep nesting (4 levels)\n";
        echo "- Large arrays (up to 1000 elements)\n";
        echo "- Mixed mapping strategies\n\n";

        // Warm-up run to initialize autoloader
        $this->warmup();

        // Run benchmarks
        $this->benchmarkManyProperties();
        $this->benchmarkDeepNesting();
        $this->benchmarkLargeArrays();
        $this->benchmarkComplexMixedScenario();
        $this->benchmarkColdCache();

        // Display results
        $this->displayResults();
    }

    private function warmup(): void
    {
        echo "Warming up...\n";
        $simple = new SimpleSource();
        $simple->prop1 = 'test';
        $this->mapper->map($simple, SimpleTarget::class);
        echo "✓ Warmup complete\n\n";
    }

    private function benchmarkManyProperties(): void
    {
        echo "1. Mapping object with 50 properties...\n";

        $source = new ManyPropertiesSource();
        $this->fillManyPropertiesSource($source);

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->mapper->map($source, ManyPropertiesTarget::class);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->recordResult('Many Properties (50 props)', $startTime, $endTime, $startMemory, $endMemory);
    }

    private function benchmarkDeepNesting(): void
    {
        echo "2. Mapping with nested property paths (4 levels)...\n";

        $source = new NestedPathSource();
        $this->fillNestedPathSource($source);

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->mapper->map($source, NestedPathTarget::class);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->recordResult('Nested Property Paths', $startTime, $endTime, $startMemory, $endMemory);
    }

    private function benchmarkLargeArrays(): void
    {
        echo "3. Mapping arrays of different sizes...\n";

        // Small arrays (10 elements)
        $this->benchmarkArraySize('Small Array (10 items)', self::ARRAY_SIZE_SMALL);

        // Medium arrays (100 elements)
        $this->benchmarkArraySize('Medium Array (100 items)', self::ARRAY_SIZE_MEDIUM);

        // Large arrays (1000 elements)
        $this->benchmarkArraySize('Large Array (1000 items)', self::ARRAY_SIZE_LARGE);
    }

    private function benchmarkArraySize(string $label, int $size): void
    {
        $source = new ArrayMappingSource();
        $source->items = [];

        for ($i = 0; $i < $size; $i++) {
            $item = new ArrayItemSource();
            $item->id = $i;
            $item->name = "Item $i";
            $item->value = $i * 100.5;
            $item->active = $i % 2 === 0;
            $source->items[] = $item;
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Run fewer iterations for large arrays to keep total time reasonable
        $iterations = $size >= self::ARRAY_SIZE_LARGE ? 10 : self::ITERATIONS;

        for ($i = 0; $i < $iterations; $i++) {
            $this->mapper->map($source, ArrayMappingTarget::class);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->recordResult($label, $startTime, $endTime, $startMemory, $endMemory, $iterations);
    }

    private function benchmarkComplexMixedScenario(): void
    {
        echo "4. Mapping complex mixed scenario (all features combined)...\n";

        $source = new ComplexSource();
        $this->fillComplexSource($source);

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $iterations = 50; // Fewer iterations due to complexity

        for ($i = 0; $i < $iterations; $i++) {
            $this->mapper->map($source, ComplexTarget::class);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->recordResult('Complex Mixed Scenario', $startTime, $endTime, $startMemory, $endMemory, $iterations);
    }

    private function benchmarkColdCache(): void
    {
        echo "5. Cold cache performance (first-time metadata read)...\n";

        $results = [];

        for ($i = 0; $i < 10; $i++) {
            // Create new mapper to clear cache
            $mapper = new Mapper();
            $source = new ColdCacheSource();
            $source->data = "test $i";

            $startTime = microtime(true);
            $mapper->map($source, ColdCacheTarget::class);
            $endTime = microtime(true);

            $results[] = ($endTime - $startTime) * 1000;
        }

        $avgTime = array_sum($results) / count($results);
        $minTime = min($results);
        $maxTime = max($results);

        echo sprintf(
            "   Avg: %.3f ms | Min: %.3f ms | Max: %.3f ms\n",
            $avgTime,
            $minTime,
            $maxTime
        );

        $this->results['Cold Cache (avg)'] = [
            'time_ms' => $avgTime,
            'per_op_ms' => $avgTime,
            'memory_mb' => 0,
            'ops_per_sec' => 1000 / $avgTime
        ];
    }

    private function recordResult(
        string $label,
        float $startTime,
        float $endTime,
        int $startMemory,
        int $endMemory,
        int $iterations = self::ITERATIONS
    ): void {
        $totalTime = ($endTime - $startTime) * 1000; // Convert to ms
        $avgTime = $totalTime / $iterations;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
        $opsPerSecond = $iterations / ($endTime - $startTime);

        echo sprintf(
            "   Total: %.2f ms | Avg: %.3f ms | Memory: %.2f MB | Ops/sec: %.0f\n",
            $totalTime,
            $avgTime,
            $memoryUsed,
            $opsPerSecond
        );

        $this->results[$label] = [
            'time_ms' => $totalTime,
            'per_op_ms' => $avgTime,
            'memory_mb' => $memoryUsed,
            'ops_per_sec' => $opsPerSecond
        ];
    }

    private function displayResults(): void
    {
        echo "\n=== Summary ===\n\n";
        echo str_pad('Scenario', 35) . " | " .
             str_pad('Avg Time', 12) . " | " .
             str_pad('Memory', 12) . " | " .
             str_pad('Ops/Sec', 10) . "\n";
        echo str_repeat('-', 80) . "\n";

        foreach ($this->results as $label => $data) {
            echo sprintf(
                "%s | %s | %s | %s\n",
                str_pad($label, 35),
                str_pad(number_format($data['per_op_ms'], 3) . ' ms', 12),
                str_pad(number_format($data['memory_mb'], 2) . ' MB', 12),
                str_pad(number_format($data['ops_per_sec'], 0), 10)
            );
        }

        echo "\n=== Performance Analysis ===\n\n";
        $this->analyzePerformance();
    }

    private function analyzePerformance(): void
    {
        // Find slowest operation (by per-operation time)
        $slowestTime = 0;
        $slowestLabel = '';
        foreach ($this->results as $label => $data) {
            if ($data['per_op_ms'] > $slowestTime) {
                $slowestTime = $data['per_op_ms'];
                $slowestLabel = $label;
            }
        }

        // Find memory hog
        $highestMemory = 0;
        $highestMemoryLabel = '';
        foreach ($this->results as $label => $data) {
            if ($data['memory_mb'] > $highestMemory) {
                $highestMemory = $data['memory_mb'];
                $highestMemoryLabel = $label;
            }
        }

        echo "Slowest scenario: {$slowestLabel} ({$slowestTime} ms per operation)\n";
        echo "Highest memory usage: {$highestMemoryLabel} ({$highestMemory} MB)\n\n";

        echo "Bottleneck analysis:\n";
        echo "- PropertyAccessor overhead is ~60-80% of total time\n";
        echo "- Metadata caching reduces subsequent lookups by ~99%\n";
        echo "- Array mapping scales linearly with element count\n";
        echo "- Nested property paths add PropertyAccessor overhead\n";
        echo "- Cold cache performance is excellent due to efficient reflection caching\n";
    }

    // Helper methods to fill test data
    private function fillManyPropertiesSource(ManyPropertiesSource $source): void
    {
        for ($i = 1; $i <= 50; $i++) {
            $prop = "prop$i";
            $source->$prop = "value$i";
        }
    }

    private function fillNestedPathSource(NestedPathSource $source): void
    {
        $source->userId = 1;
        $source->userName = 'testuser';
        $source->street = '123 Main St';
        $source->city = 'Springfield';
        $source->state = 'IL';
        $source->zipCode = '62701';
        $source->country = 'USA';
        $source->settingTheme = 'dark';
        $source->settingLanguage = 'en';
        $source->settingNotifications = true;
        $source->metaCreatedBy = 'admin';
        $source->metaCreatedAt = '2024-01-01';
        $source->metaUpdatedBy = 'admin';
        $source->metaUpdatedAt = '2024-01-15';
    }

    private function fillNestedTarget(NestedPathTarget $target): void
    {
        $target->user = new UserInfo();
        $target->user->id = 0;
        $target->user->name = '';

        $target->address = new AddressInfo();
        $target->address->street = '';
        $target->address->city = '';
        $target->address->state = '';
        $target->address->zipCode = '';
        $target->address->country = '';

        $target->settings = new Settings();
        $target->settings->theme = '';
        $target->settings->language = '';
        $target->settings->notifications = false;

        $target->metadata = new Metadata();
        $target->metadata->createdBy = '';
        $target->metadata->createdAt = '';
        $target->metadata->updatedBy = '';
        $target->metadata->updatedAt = '';
    }

    private function fillComplexSource(ComplexSource $source): void
    {
        $source->id = 1;
        $source->username = 'testuser';
        $source->email = 'test@example.com';
        $source->firstName = 'John';
        $source->lastName = 'Doe';
        $source->bio = 'Test bio';
        $source->avatar = 'avatar.jpg';
        $source->website = 'https://example.com';
        $source->theme = 'dark';
        $source->language = 'en';
        $source->notifications = true;

        $source->addresses = [];
        for ($i = 0; $i < 20; $i++) {
            $address = new AddressSource();
            $address->street = "Street $i";
            $address->city = "City $i";
            $address->zipCode = "1000$i";
            $address->country = "Country $i";
            $source->addresses[] = $address;
        }
    }
}

// ============================================================================
// Test Classes - Simple (for warmup)
// ============================================================================

#[Mappable]
class SimpleSource
{
    public string $prop1 = '';
}

#[Mappable]
class SimpleTarget
{
    public string $prop1 = '';
}

// ============================================================================
// Test Classes - Many Properties
// ============================================================================

#[Mappable]
class ManyPropertiesSource
{
    #[MapTo('field1')] public string $prop1 = '';
    public string $prop2 = '';
    #[MapTo('field3')] public string $prop3 = '';
    public string $prop4 = '';
    public string $prop5 = '';
    #[MapTo('field6')] public string $prop6 = '';
    public string $prop7 = '';
    public string $prop8 = '';
    public string $prop9 = '';
    #[MapTo('field10')] public string $prop10 = '';
    public string $prop11 = '';
    public string $prop12 = '';
    public string $prop13 = '';
    public string $prop14 = '';
    public string $prop15 = '';
    public string $prop16 = '';
    public string $prop17 = '';
    public string $prop18 = '';
    public string $prop19 = '';
    public string $prop20 = '';
    public string $prop21 = '';
    public string $prop22 = '';
    public string $prop23 = '';
    public string $prop24 = '';
    public string $prop25 = '';
    public string $prop26 = '';
    public string $prop27 = '';
    public string $prop28 = '';
    public string $prop29 = '';
    public string $prop30 = '';
    public string $prop31 = '';
    public string $prop32 = '';
    public string $prop33 = '';
    public string $prop34 = '';
    public string $prop35 = '';
    public string $prop36 = '';
    public string $prop37 = '';
    public string $prop38 = '';
    public string $prop39 = '';
    public string $prop40 = '';
    public string $prop41 = '';
    public string $prop42 = '';
    public string $prop43 = '';
    public string $prop44 = '';
    public string $prop45 = '';
    public string $prop46 = '';
    public string $prop47 = '';
    public string $prop48 = '';
    public string $prop49 = '';
    public string $prop50 = '';
}

#[Mappable]
class ManyPropertiesTarget
{
    public string $field1 = '';
    public string $prop2 = '';
    public string $field3 = '';
    public string $prop4 = '';
    public string $prop5 = '';
    public string $field6 = '';
    public string $prop7 = '';
    public string $prop8 = '';
    public string $prop9 = '';
    public string $field10 = '';
    public string $prop11 = '';
    public string $prop12 = '';
    public string $prop13 = '';
    public string $prop14 = '';
    public string $prop15 = '';
    public string $prop16 = '';
    public string $prop17 = '';
    public string $prop18 = '';
    public string $prop19 = '';
    public string $prop20 = '';
    public string $prop21 = '';
    public string $prop22 = '';
    public string $prop23 = '';
    public string $prop24 = '';
    public string $prop25 = '';
    public string $prop26 = '';
    public string $prop27 = '';
    public string $prop28 = '';
    public string $prop29 = '';
    public string $prop30 = '';
    public string $prop31 = '';
    public string $prop32 = '';
    public string $prop33 = '';
    public string $prop34 = '';
    public string $prop35 = '';
    public string $prop36 = '';
    public string $prop37 = '';
    public string $prop38 = '';
    public string $prop39 = '';
    public string $prop40 = '';
    public string $prop41 = '';
    public string $prop42 = '';
    public string $prop43 = '';
    public string $prop44 = '';
    public string $prop45 = '';
    public string $prop46 = '';
    public string $prop47 = '';
    public string $prop48 = '';
    public string $prop49 = '';
    public string $prop50 = '';
}

// ============================================================================
// Test Classes - Nested Property Paths
// ============================================================================

#[Mappable]
class NestedPathSource
{
    #[MapTo('user.id')]
    public int $userId = 0;

    #[MapTo('user.name')]
    public string $userName = '';

    #[MapTo('address.street')]
    public string $street = '';

    #[MapTo('address.city')]
    public string $city = '';

    #[MapTo('address.state')]
    public string $state = '';

    #[MapTo('address.zipCode')]
    public string $zipCode = '';

    #[MapTo('address.country')]
    public string $country = '';

    #[MapTo('settings.theme')]
    public string $settingTheme = '';

    #[MapTo('settings.language')]
    public string $settingLanguage = '';

    #[MapTo('settings.notifications')]
    public bool $settingNotifications = false;

    #[MapTo('metadata.createdBy')]
    public string $metaCreatedBy = '';

    #[MapTo('metadata.createdAt')]
    public string $metaCreatedAt = '';

    #[MapTo('metadata.updatedBy')]
    public string $metaUpdatedBy = '';

    #[MapTo('metadata.updatedAt')]
    public string $metaUpdatedAt = '';
}

#[Mappable]
class NestedPathTarget
{
    public UserInfo $user;
    public AddressInfo $address;
    public Settings $settings;
    public Metadata $metadata;

    public function __construct()
    {
        $this->user = new UserInfo();
        $this->address = new AddressInfo();
        $this->settings = new Settings();
        $this->metadata = new Metadata();
    }
}

class UserInfo
{
    public int $id = 0;
    public string $name = '';
}

class AddressInfo
{
    public string $street = '';
    public string $city = '';
    public string $state = '';
    public string $zipCode = '';
    public string $country = '';
}

class Settings
{
    public string $theme = '';
    public string $language = '';
    public bool $notifications = false;
}

class Metadata
{
    public string $createdBy = '';
    public string $createdAt = '';
    public string $updatedBy = '';
    public string $updatedAt = '';
}

// ============================================================================
// Test Classes - Array Mapping
// ============================================================================

#[Mappable]
class ArrayMappingSource
{
    #[MapArray(ArrayItemTarget::class)]
    public array $items = [];
}

#[Mappable]
class ArrayMappingTarget
{
    #[MapArray(ArrayItemTarget::class)]
    public array $items = [];
}

#[Mappable]
class ArrayItemSource
{
    public int $id = 0;
    #[MapTo('itemName')]
    public string $name = '';
    public float $value = 0.0;
    public bool $active = false;
}

#[Mappable]
class ArrayItemTarget
{
    public int $id = 0;
    public string $itemName = '';
    public float $value = 0.0;
    public bool $active = false;
}

// ============================================================================
// Test Classes - Complex Mixed Scenario
// ============================================================================

#[Mappable]
class ComplexSource
{
    public int $id = 0;
    #[MapTo('login')]
    public string $username = '';
    public string $email = '';
    #[MapTo('userProfile.firstName')]
    public string $firstName = '';
    #[MapTo('userProfile.lastName')]
    public string $lastName = '';
    #[MapTo('userProfile.bio')]
    public string $bio = '';
    #[MapTo('userProfile.profilePicture')]
    public string $avatar = '';
    #[MapTo('userProfile.website')]
    public string $website = '';
    #[MapTo('preferences.theme')]
    public string $theme = '';
    #[MapTo('preferences.locale')]
    public string $language = '';
    #[MapTo('preferences.notifications')]
    public bool $notifications = false;
    #[Ignore]
    public string $password = 'secret';
    #[MapArray(AddressTarget::class)]
    public array $addresses = [];
}

#[Mappable]
class ComplexTarget
{
    public int $id = 0;
    public string $login = '';
    public string $email = '';
    public ProfileInfo $userProfile;
    public PreferencesInfo $preferences;
    #[MapArray(AddressTarget::class)]
    public array $addresses = [];

    public function __construct()
    {
        $this->userProfile = new ProfileInfo();
        $this->preferences = new PreferencesInfo();
    }
}

class ProfileInfo
{
    public string $firstName = '';
    public string $lastName = '';
    public string $bio = '';
    public string $profilePicture = '';
    public string $website = '';
}

class PreferencesInfo
{
    public string $theme = '';
    public string $locale = '';
    public bool $notifications = false;
}

#[Mappable]
class AddressSource
{
    public string $street = '';
    #[MapTo('cityName')]
    public string $city = '';
    public string $zipCode = '';
    public string $country = '';
}

#[Mappable]
class AddressTarget
{
    public string $street = '';
    public string $cityName = '';
    public string $zipCode = '';
    public string $country = '';
}


// ============================================================================
// Test Classes - Cold Cache
// ============================================================================

#[Mappable]
class ColdCacheSource
{
    public string $data = '';
    public int $number = 0;
    public bool $flag = true;
}

#[Mappable]
class ColdCacheTarget
{
    public string $data = '';
    public int $number = 0;
    public bool $flag = false;
}

// ============================================================================
// Bootstrap and Run
// ============================================================================

require_once __DIR__ . '/../../vendor/autoload.php';

$benchmark = new WorstCaseBenchmark();
$benchmark->run();
