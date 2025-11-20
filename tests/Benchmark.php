<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Tests;

// Bootstrap autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Alecszaharia\Simmap\Mapper;
use Alecszaharia\Simmap\Tests\Fixtures\Company;
use Alecszaharia\Simmap\Tests\Fixtures\CompanyDTO;
use Alecszaharia\Simmap\Tests\Fixtures\User;
use Alecszaharia\Simmap\Tests\Fixtures\UserDTO;

/**
 * Benchmark script to measure Mapper performance
 *
 * Run with: php tests/Benchmark.php
 */
final class Benchmark
{
    private Mapper $mapper;
    private array $results = [];

    public function __construct()
    {
        $this->mapper = new Mapper();
    }

    /**
     * Run all benchmarks
     */
    public function run(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║          SIMMAP OBJECT MAPPER PERFORMANCE BENCHMARK            ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";

        echo "System Information:\n";
        echo "  PHP Version: " . phpversion() . "\n";
        echo "  OPcache: " . (extension_loaded('Zend OPcache') ? 'enabled' : 'disabled') . "\n";
        echo "  JIT: " . (ini_get('opcache.jit') ? 'enabled' : 'disabled') . "\n";
        echo "\n";

        // Warmup
        echo "Warming up JIT and caches...\n";
        $this->warmup();
        echo "\n";

        // Run benchmarks
        $this->benchmarkSimpleMapping();
        $this->benchmarkNestedPropertyMapping();
        $this->benchmarkArrayMapping();
        $this->benchmarkBatchMapping();
        $this->benchmarkReverseMapping();
        $this->benchmarkRepeatedMappingSameClass();
        $this->benchmarkComplexMappingScenario();

        // Summary
        $this->printSummary();
    }

    /**
     * Warmup run to stabilize JIT compilation
     */
    private function warmup(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $user = $this->createUser();
            $this->mapper->map($user, UserDTO::class);
        }
    }

    /**
     * Benchmark 1: Simple mapping (no nested properties)
     */
    private function benchmarkSimpleMapping(): void
    {
        $iterations = 10000;
        $user = $this->createUser();

        $startTime = hrtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->mapper->map($user, UserDTO::class);
        }

        $this->recordBenchmark(
            'Simple Mapping (2 properties)',
            $iterations,
            $startTime,
            $startMemory
        );
    }

    /**
     * Benchmark 2: Nested property mapping
     */
    private function benchmarkNestedPropertyMapping(): void
    {
        $iterations = 5000;
        $user = $this->createUser();
        $user->profile->bio = 'Software Engineer with 10 years experience';

        $startTime = hrtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->mapper->map($user, UserDTO::class);
        }

        $this->recordBenchmark(
            'Nested Property Mapping (with profile.bio)',
            $iterations,
            $startTime,
            $startMemory
        );
    }

    /**
     * Benchmark 3: Array/Collection mapping
     */
    private function benchmarkArrayMapping(): void
    {
        $iterations = 1000;

        // Create company with employees
        $company = new Company();
        $company->name = 'Tech Corp';

        for ($i = 0; $i < 10; $i++) {
            $employee = $this->createUser();
            $employee->email = "employee{$i}@techcorp.com";
            $company->employees[] = $employee;
        }

        $startTime = hrtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->mapper->map($company, CompanyDTO::class);
        }

        $this->recordBenchmark(
            'Array Mapping (10 employee objects)',
            $iterations,
            $startTime,
            $startMemory
        );
    }

    /**
     * Benchmark 4: Batch mapping many objects
     */
    private function benchmarkBatchMapping(): void
    {
        $batches = 100;
        $objectsPerBatch = 100;

        $startTime = hrtime(true);
        $startMemory = memory_get_usage(true);

        for ($batch = 0; $batch < $batches; $batch++) {
            for ($i = 0; $i < $objectsPerBatch; $i++) {
                $user = $this->createUser();
                $this->mapper->map($user, UserDTO::class);
            }
        }

        $this->recordBenchmark(
            'Batch Mapping (10,000 objects total)',
            $batches * $objectsPerBatch,
            $startTime,
            $startMemory
        );
    }

    /**
     * Benchmark 5: Reverse mapping (DTO back to Entity)
     */
    private function benchmarkReverseMapping(): void
    {
        $iterations = 5000;

        // Create DTO
        $dto = new UserDTO();
        $dto->email = 'test@example.com';
        $dto->fullName = 'John Doe';
        $dto->age = 30;
        $dto->biography = 'Full stack developer';

        $startTime = hrtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->mapper->map($dto, User::class);
        }

        $this->recordBenchmark(
            'Reverse Mapping (DTO → Entity)',
            $iterations,
            $startTime,
            $startMemory
        );
    }

    /**
     * Benchmark 6: Repeated mapping of same class (cache effectiveness)
     */
    private function benchmarkRepeatedMappingSameClass(): void
    {
        $iterations = 10000;
        $user = $this->createUser();

        // Clear any potential caches
        $startTime = hrtime(true);
        $startMemory = memory_get_usage(true);

        // All iterations map the same source to the same target class
        for ($i = 0; $i < $iterations; $i++) {
            $this->mapper->map($user, UserDTO::class);
        }

        $this->recordBenchmark(
            'Repeated Mapping (same class)',
            $iterations,
            $startTime,
            $startMemory,
            true  // is_cache_test = true
        );
    }

    /**
     * Benchmark 7: Complex real-world scenario
     */
    private function benchmarkComplexMappingScenario(): void
    {
        $iterations = 1000;

        // Create complex object graph
        $company = new Company();
        $company->name = 'Global Enterprise';

        // Add 20 employees
        for ($i = 0; $i < 20; $i++) {
            $user = $this->createUser();
            $user->name = "Employee {$i}";
            $user->email = "employee{$i}@enterprise.com";
            $user->profile->bio = "Expert in domain {$i}";
            $company->employees[] = $user;
        }

        $startTime = hrtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $dto = $this->mapper->map($company, CompanyDTO::class);

            // Also reverse map back
            $this->mapper->map($dto, Company::class);
        }

        $this->recordBenchmark(
            'Complex Scenario (20 employees + reverse mapping)',
            $iterations * 2,  // Count both forward and reverse
            $startTime,
            $startMemory
        );
    }

    /**
     * Record benchmark results
     */
    private function recordBenchmark(
        string $name,
        int $iterations,
        int $startTime,
        int $startMemory,
        bool $cacheTest = false
    ): void {
        $endTime = hrtime(true);
        $endMemory = memory_get_usage(true);

        $totalTime = ($endTime - $startTime) / 1_000_000;  // nanoseconds to milliseconds
        $timePerOp = $totalTime / $iterations;
        $memoryUsed = ($endMemory - $startMemory) / 1024;  // bytes to KB
        $memoryPerOp = $memoryUsed / $iterations;
        $throughput = $iterations / ($totalTime / 1000);  // operations per second

        $this->results[] = [
            'name' => $name,
            'iterations' => $iterations,
            'totalTime' => $totalTime,
            'timePerOp' => $timePerOp,
            'memory' => $memoryUsed,
            'memoryPerOp' => $memoryPerOp,
            'throughput' => $throughput,
            'isCacheTest' => $cacheTest,
        ];

        // Print result
        printf(
            "%-50s │ %8d ops │ %10.2f ms │ %8.3f ms/op │ %8.1f ops/s\n",
            $name,
            $iterations,
            $totalTime,
            $timePerOp,
            $throughput
        );
    }

    /**
     * Print summary table
     */
    private function printSummary(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                      DETAILED RESULTS                          ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";

        echo sprintf(
            "%-50s │ %12s │ %12s │ %15s │ %12s\n",
            'Test',
            'Total Time',
            'Per Operation',
            'Memory (KB)',
            'Per Op (KB)'
        );
        echo str_repeat('─', 120) . "\n";

        foreach ($this->results as $result) {
            echo sprintf(
                "%-50s │ %12.2f ms │ %12.3f ms │ %15.2f │ %12.4f\n",
                $result['name'],
                $result['totalTime'],
                $result['timePerOp'],
                $result['memory'],
                $result['memoryPerOp']
            );
        }

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                   PERFORMANCE METRICS                          ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";

        // Find fastest and slowest
        $fastest = min(array_column($this->results, 'timePerOp'));
        $slowest = max(array_column($this->results, 'timePerOp'));
        $avgMemoryPerOp = array_sum(array_column($this->results, 'memoryPerOp')) / count($this->results);

        echo "Fastest operation: " . number_format($fastest * 1000, 2) . " μs\n";
        echo "Slowest operation: " . number_format($slowest * 1000, 2) . " μs\n";
        echo "Average memory per operation: " . number_format($avgMemoryPerOp, 4) . " KB\n";
        echo "\n";

        // Cache effectiveness test
        $cacheTest = array_filter($this->results, fn($r) => $r['isCacheTest']);
        if (!empty($cacheTest)) {
            $cacheResult = reset($cacheTest);
            echo sprintf(
                "Cache effectiveness (repeated same class):\n  %.3f ms per operation (%.0f ops/second)\n",
                $cacheResult['timePerOp'],
                $cacheResult['throughput']
            );
        }

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                        RECOMMENDATIONS                         ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";

        // Performance analysis
        $avgTimePerOp = array_sum(array_column($this->results, 'timePerOp')) / count($this->results);

        if ($avgTimePerOp > 5) {
            echo "⚠️  CRITICAL: Average operation time is very high (> 5ms)\n";
            echo "   → Consider implementing metadata caching\n";
            echo "   → Implement reflection object caching\n";
        } elseif ($avgTimePerOp > 1) {
            echo "⚠️  WARNING: Average operation time is high (> 1ms)\n";
            echo "   → Performance optimizations would be beneficial\n";
        } else {
            echo "✓ Performance is acceptable (< 1ms per operation)\n";
        }

        // Check throughput
        $avgThroughput = array_sum(array_column($this->results, 'throughput')) / count($this->results);
        if ($avgThroughput < 100) {
            echo "⚠️  Throughput is low (< 100 ops/second)\n";
            echo "   → Review caching strategies\n";
        } elseif ($avgThroughput > 1000) {
            echo "✓ Excellent throughput (> 1000 ops/second)\n";
        }

        echo "\n";
    }

    /**
     * Create a user object for testing
     */
    private function createUser(): User
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->age = 30;
        $user->profile->bio = 'Software Developer';
        $user->setPassword('secret123');
        return $user;
    }
}

// Run benchmark
$benchmark = new Benchmark();
$benchmark->run();