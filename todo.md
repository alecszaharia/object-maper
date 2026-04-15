# Mapper Class - Code Review Todo List

## CRITICAL ISSUES

### 1. Circular Reference Detection is Incomplete
**Location**: `src/Mapper.php:360-375` - `mapArray()` method
**Severity**: CRITICAL
**Priority**: P0

**Problem**:
The circular reference detection only tracks items within array mapping (`$this->mappingStack`), not the entire object graph. If object A contains object B, and B contains A (outside of arrays), infinite recursion will occur.

**Example**:
```php
class Parent {
    public Child $child;
}
class Child {
    public Parent $parent; // Circular reference not caught
}
```

**Impact**: Stack overflow, application crash

**Remediation**:
- Implement global object tracking across the entire mapping operation, not just arrays
- Move `mappingStack` management to the main `map()` method
- Track objects by `spl_object_id()` instead of class name to handle multiple instances

---

### 2. Memory Leak in mappingStack
**Location**: `src/Mapper.php:367-391` - `mapArray()` method
**Severity**: CRITICAL
**Priority**: P0

**Problem**:
If an exception is thrown after line 369 but before lines 375/378 (unset), the `mappingStack` entry persists for the entire Mapper instance lifetime. Since Mappers are typically long-lived, this accumulates.

**Current Code**:
```php
$this->mappingStack[$itemClass] = true;
$mappedItems[$key] = $this->map($item, $targetItemClass);
unset($this->mappingStack[$itemClass]); // Might not execute
```

**Impact**:
- False circular reference detections
- Memory accumulation
- Incorrect behavior after first exception

**Remediation**:
```php
try {
    $this->mappingStack[$itemClass] = true;
    $mappedItems[$key] = $this->map($item, $targetItemClass);
} finally {
    unset($this->mappingStack[$itemClass]);
}
```

---

## HIGH SEVERITY ISSUES

### 3. Unbounded Metadata Cache Growth
**Location**: `src/Mapper.php:41, 110-125` - `$metadataCache` property
**Severity**: HIGH
**Priority**: P1

**Problem**:
The metadata cache grows indefinitely without limits. In applications mapping many different class combinations (e.g., multi-tenant systems, dynamic class generation), this causes unbounded memory growth.

**Impact**: Memory exhaustion in long-running processes (workers, daemons, etc.)

**Remediation**:
- Implement cache size limits (e.g., 1000 entries)
- Add LRU (Least Recently Used) eviction strategy
- Provide `clearMetadataCache()` public method
- Add configuration option for cache size
- Document cache behavior in class docblock

---

### 4. Null Handling Inconsistency in mapArray()
**Location**: `src/Mapper.php:326-329` - `mapArray()` method
**Severity**: HIGH
**Priority**: P1

**Problem**:
When `$sourceArray === null`, the method returns empty array `[]`. This loses the distinction between null and empty array, which may be semantically important in business logic.

**Current Code**:
```php
if ($sourceArray === null) {
    return [];
}
```

**Impact**:
- Data loss (null becomes empty array)
- Semantic incorrectness
- Breaks null coalescence patterns

**Remediation**:
```php
if ($sourceArray === null) {
    return null;
}
```

**Note**: This may require updating the return type to `array|object|null`

---

### 5. Missing Validation for Nested Object Type
**Location**: `src/Mapper.php:260-292` - `instantiateNestedObject()` method
**Severity**: HIGH
**Priority**: P1

**Problem**:
When instantiating nested objects, there's no check for whether the class is marked with `#[Mappable]`. This could instantiate objects that shouldn't be auto-created or that aren't properly configured.

**Impact**:
- Unexpected object creation
- Potential security issues
- Violation of mapping contract

**Remediation**:
- Check if the nested class has `#[Mappable]` attribute before instantiation
- Throw descriptive exception if not properly configured
- Document this requirement in attribute documentation

---

## MEDIUM SEVERITY ISSUES

### 6. No Maximum Recursion Depth for Nested Paths
**Location**: `src/Mapper.php:214-250` - `ensureNestedPathExists()` method
**Severity**: MEDIUM
**Priority**: P2

**Problem**:
No limit on nested path depth (e.g., "a.b.c.d.e.f.g.h.i.j.k..."). Maliciously crafted attributes could cause performance degradation or stack issues.

**Impact**:
- Performance degradation
- Potential DoS attack vector
- Resource exhaustion

**Remediation**:
- Set reasonable maximum depth (e.g., 10 levels)
- Throw `MappingException::nestedPathTooDeep()` if exceeded
- Make depth configurable via constructor parameter
- Document the limit in class docblock

---

### 7. Exception in ensureNestedPathExists Silently Swallowed
**Location**: `src/Mapper.php:245-247` - `ensureNestedPathExists()` catch block
**Severity**: MEDIUM
**Priority**: P2

**Problem**:
When `PropertyAccessException` occurs, the method just returns without logging or context. This makes debugging difficult when nested path initialization fails.

**Current Code**:
```php
} catch (PropertyAccessException $e) {
    // If we can't access the property, let it fail naturally in setValue
    return;
}
```

**Impact**:
- Silent failures
- Difficult debugging
- Lost error context

**Remediation**:
- Log the exception with context (property path, object class)
- Consider adding debug mode that throws instead of swallowing
- Add comment explaining when this is expected vs unexpected

---

### 8. No Validation of targetClass Parameter
**Location**: `src/Mapper.php:76-78` - `map()` method
**Severity**: MEDIUM
**Priority**: P2

**Problem**:
When `$target` is a string, there's no validation that it's a valid class name before attempting instantiation. Invalid strings proceed to `instantiateTarget()` which then throws generic ReflectionException.

**Impact**:
- Poor error messages
- Delayed validation
- Harder to debug for users

**Remediation**:
```php
if (is_string($target)) {
    if (!class_exists($target)) {
        throw MappingException::invalidTargetClass($target);
    }
    $targetClass = $target;
    $target = $this->instantiateTarget($targetClass);
}
```

---

## LOW SEVERITY / CODE QUALITY ISSUES

### 9. Inconsistent Property Access Pattern
**Location**: Throughout the class
**Severity**: LOW
**Priority**: P3

**Problem**:
Mix of direct property access and method calls. For example:
- `$metadata->isValidMapping` (line 87) - direct property
- `$metadata->getMappingsForDirection()` (line 92) - method call

**Impact**:
- Inconsistent API
- Harder to maintain
- Confusing for code readers

**Remediation**:
- Use consistent accessor patterns (preferably all methods)
- Consider making `MappingMetadata` properties private with getters
- Update all access points to use methods

---

### 10. Magic Number in Array Iteration
**Location**: `src/Mapper.php:337-343` - `mapArray()` method
**Severity**: LOW
**Priority**: P3

**Problem**:
The `$index` variable is incremented separately from the foreach loop, including in the null case. This adds unnecessary complexity.

**Current Code**:
```php
$index = 0;
foreach ($items as $key => $item) {
    if ($item === null) {
        $mappedItems[$key] = null;
        $index++;
        continue;
    }
    // ... mapping logic ...
    $index++;
}
```

**Impact**: Maintenance complexity, potential for bugs

**Remediation**:
- Use `array_values()` if you need numeric index
- Or track index only in error messages: `array_search($key, array_keys($items))`
- Simplify the loop structure

---

## TESTING GAPS

### 11. Missing Test: Deeply Nested Paths (5+ levels)
**Severity**: MEDIUM
**Priority**: P2

**Test to Add**:
```php
public function testDeeplyNestedPaths(): void
{
    // Test mapping with path like "a.b.c.d.e.f"
    // Verify all intermediate objects are created
    // Test performance with very deep nesting
}
```

---

### 12. Missing Test: Circular References Outside Arrays
**Severity**: CRITICAL
**Priority**: P0

**Test to Add**:
```php
public function testCircularReferenceDetection(): void
{
    // Create Parent with Child property
    // Child has Parent property creating circular reference
    // Expect MappingException::circularReferenceDetected
}
```

---

### 13. Missing Test: Invalid Class Name as Target Parameter
**Severity**: MEDIUM
**Priority**: P2

**Test to Add**:
```php
public function testInvalidClassNameThrowsException(): void
{
    $dto = new UserDTO();
    $this->expectException(MappingException::class);
    $this->expectExceptionMessage('invalid class');

    $this->mapper->map($dto, 'NonExistentClass');
}
```

---

### 14. Missing Test: Exception During Nested Object Instantiation
**Severity**: HIGH
**Priority**: P1

**Test to Add**:
```php
public function testNestedObjectInstantiationFailure(): void
{
    // Test with nested object that can't be instantiated
    // Expect clear error message about which property failed
}
```

---

### 15. Missing Test: Large Metadata Cache Behavior
**Severity**: HIGH
**Priority**: P1

**Test to Add**:
```php
public function testMetadataCacheMemoryLimits(): void
{
    // Map 10,000 different class pairs
    // Verify memory usage doesn't grow unbounded
    // Test cache eviction if implemented
}
```

---

## PERFORMANCE OPTIMIZATIONS

### 16. Inefficient Cache Key Building
**Location**: `src/Mapper.php:136-142` - `buildCacheKey()` method
**Severity**: LOW
**Priority**: P3

**Problem**:
String concatenation with array sorting on every call. For frequently mapped class pairs, this allocates unnecessary arrays.

**Current Code**:
```php
private function buildCacheKey(string $classA, string $classB): string
{
    $classes = [$classA, $classB];
    sort($classes);
    return implode('<->', $classes);
}
```

**Impact**: Minor performance overhead in hot paths

**Optimization**:
```php
private function buildCacheKey(string $classA, string $classB): string
{
    // Simple comparison is faster than array allocation + sort
    return $classA < $classB
        ? $classA . '<->' . $classB
        : $classB . '<->' . $classA;
}
```

---

## SUMMARY

- **Critical Issues**: 2 (circular references, memory leak)
- **High Severity**: 4 (cache growth, null handling, validation issues)
- **Medium Severity**: 3 (recursion limits, error handling, validation)
- **Low Severity**: 2 (code quality, performance)
- **Testing Gaps**: 5 (edge cases)

**Total Items**: 16

**Recommended Order of Implementation**:
1. Fix circular reference detection (Issue #1)
2. Fix memory leak in mappingStack (Issue #2)
3. Add test for circular references (Issue #12)
4. Implement metadata cache limits (Issue #3)
5. Fix null handling in arrays (Issue #4)
6. Add validation for nested objects (Issue #5)
7. Add remaining tests (Issues #11, #13, #14, #15)
8. Address medium severity issues (Issues #6, #7, #8)
9. Code quality improvements (Issues #9, #10)
10. Performance optimization (Issue #16)
