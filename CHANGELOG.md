# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Makefile with common development tasks (test, test-coverage, test-file, test-filter, example, clean)
- Testing section in README.md with Makefile usage examples
- Forward mapping index in `MappingMetadata` for O(1) property lookups in both directions
- Comprehensive test suite for `MappingMetadata` (25 tests covering indexes, duplicates, performance)
- Duplicate prevention logic for property mappings (throws `InvalidArgumentException`)
- Performance documentation for O(1) lookup guarantees

### Changed
- Updated CLAUDE.md to reference Makefile commands instead of raw Docker commands
- Updated CONTRIBUTING.md to use Makefile for testing
- `MappingMetadata::findTargetProperty()` now uses O(1) forward index instead of O(n) linear search
- `MappingMetadata::isPropertyIgnored()` now uses O(1) associative array lookup instead of `in_array()`
- `MappingMetadata` constructor now normalizes ignored properties to associative array
- Updated architecture documentation to reflect dual-index implementation

### Fixed
- Performance asymmetry between forward and reverse property lookups
- Potential duplicate property mappings that could cause undefined behavior
- Inefficient ignored property checks with large property lists

## [1.0.0] - 2025-01-15

### Added
- Initial release of Simmap - Symmetrical Object Mapper
- Attribute-based configuration using PHP 8.1+ attributes
- Symmetrical bidirectional mapping (A→B and B→A)
- Support for nested property paths (e.g., `user.address.city`)
- Auto-mapping for properties with matching names
- `#[MapTo]` attribute for custom property mapping
- `#[Ignore]` attribute to exclude properties from mapping
- PropertyAccess integration for robust property access
- Full PHP 8.1+ type hints with strict types
- Support for mapping to existing object instances
- Symfony integration compatibility
- Comprehensive documentation and examples

### Features
- **Mapper class**: Core mapping functionality
- **MapperInterface**: Contract for mapper implementations
- **MetadataReader**: Reflection-based attribute reading
- **MappingMetadata**: Property mapping metadata container
- **PropertyMapping**: Individual property mapping representation
- **MappingException**: Custom exception for mapping errors

### Requirements
- PHP >= 8.1
- Symfony PropertyAccess component ^6.0 or ^7.0

[Unreleased]: https://github.com/alecszaharia/simmap/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/alecszaharia/simmap/releases/tag/v1.0.0
