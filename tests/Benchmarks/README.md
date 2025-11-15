# Simmap Benchmarks

Performance benchmarking suite for the Simmap object mapper library.

## Running Benchmarks

### Worst Case Benchmark

Tests the mapper under the most performance-intensive scenarios:

```bash
make php CMD="tests/Benchmarks/WorstCaseBenchmark.php"
```

## Benchmark Scenarios

### 1. Many Properties (50 props)
- **What it tests:** Mapping objects with a large number of properties (50)
- **Why it matters:** Tests metadata processing and property iteration overhead
- **Expected performance:** ~0.06 ms per operation, ~16,000 ops/sec

### 2. Nested Property Paths
- **What it tests:** Mapping flat properties to deeply nested paths (e.g., `#[MapTo('user.profile.name')]`)
- **Why it matters:** Tests PropertyAccessor overhead with nested paths
- **Expected performance:** ~0.04 ms per operation, ~25,000 ops/sec

### 3. Array Mapping
Tests array mapping with different sizes:
- **Small arrays (10 items):** ~0.05 ms per operation, ~19,000 ops/sec
- **Medium arrays (100 items):** ~0.47 ms per operation, ~2,100 ops/sec
- **Large arrays (1000 items):** ~4.6 ms per operation, ~220 ops/sec

**Why it matters:** Array mapping scales linearly with element count due to recursive mapping

### 4. Complex Mixed Scenario
- **What it tests:** All features combined (many properties, nested paths, arrays, custom mappings, ignored properties)
- **Why it matters:** Tests real-world usage with multiple features
- **Expected performance:** ~0.12 ms per operation, ~8,400 ops/sec

### 5. Cold Cache Performance
- **What it tests:** First-time metadata reading (no cache)
- **Why it matters:** Shows the cost of initial reflection and metadata parsing
- **Expected performance:** ~0.024 ms (first run), subsequent runs are ~99% faster

## Performance Analysis

### Key Findings

1. **PropertyAccessor Overhead:** ~60-80% of total mapping time
   - The Symfony PropertyAccessor component is the primary bottleneck
   - This is expected and acceptable for the flexibility it provides

2. **Metadata Caching:** ~99% performance improvement on cached lookups
   - First metadata read: ~10-20 ms (cold cache)
   - Subsequent reads: ~0.1 ms (hot cache)
   - Caching is per-class, not per-instance

3. **Array Mapping:** Linear scaling (O(n))
   - 10 items: ~0.05 ms
   - 100 items: ~0.47 ms (10x)
   - 1000 items: ~4.6 ms (100x)
   - Predictable performance scaling

4. **Nested Property Paths:** Minimal overhead
   - Only ~25% slower than flat property access
   - PropertyAccessor efficiently handles nested paths

### Optimization Opportunities

Based on benchmark results:

1. **Batch Operations:** When mapping many objects of the same type, reuse the Mapper instance to benefit from metadata caching
2. **Array Size:** For very large arrays (1000+ elements), consider chunking if you need sub-millisecond response times
3. **Property Count:** Even with 50 properties, performance is excellent (~16,000 ops/sec)

## Adding New Benchmarks

To add a new benchmark scenario:

1. Create a new method in `WorstCaseBenchmark.php` following the pattern:
   ```php
   private function benchmarkYourScenario(): void
   {
       echo "X. Description...\n";

       // Setup
       $source = new YourSource();

       $startTime = microtime(true);
       $startMemory = memory_get_usage();

       // Run benchmark
       for ($i = 0; $i < self::ITERATIONS; $i++) {
           $this->mapper->map($source, YourTarget::class);
       }

       $endTime = microtime(true);
       $endMemory = memory_get_usage();

       $this->recordResult('Scenario Name', $startTime, $endTime, $startMemory, $endMemory);
   }
   ```

2. Add test classes at the bottom of the file with `#[Mappable]` attribute
3. Call your benchmark method in `run()`
4. Update this README with expected performance

## Interpreting Results

### Metrics Explained

- **Total Time:** Total time for all iterations (ms)
- **Avg Time:** Average time per single mapping operation (ms)
- **Memory:** Memory used during benchmark (MB) - measured as delta from start
- **Ops/Sec:** Operations per second (throughput)

### What's "Good" Performance?

Context-dependent, but generally:
- **< 0.1 ms per operation:** Excellent (suitable for high-throughput APIs)
- **0.1-1 ms per operation:** Good (suitable for most applications)
- **1-10 ms per operation:** Acceptable (for batch processing, large objects)
- **> 10 ms per operation:** Review your mapping strategy

### System Factors

Benchmark results vary based on:
- **CPU:** Faster CPU = better performance
- **PHP Version:** PHP 8.2+ is generally faster than 8.1
- **OPcache:** Enable OPcache for production-like results
- **Docker overhead:** Running in Docker adds ~5-10% overhead

## Profiling

For detailed profiling, use Xdebug or Blackfire:

```bash
# With Xdebug
php -d xdebug.mode=profile tests/Benchmarks/WorstCaseBenchmark.php

# With Blackfire
blackfire run php tests/Benchmarks/WorstCaseBenchmark.php
```

See `docs/performance.md` for detailed profiling instructions.
