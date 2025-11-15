# Contributing to Simmap

Thank you for your interest in contributing to Simmap! This document provides guidelines and instructions for contributing.

## Code of Conduct

- Be respectful and inclusive
- Focus on constructive feedback
- Help create a welcoming environment for all contributors

## Getting Started

### Prerequisites

- PHP >= 8.1
- Composer
- Docker (for running tests in isolated environment)

### Setting Up Development Environment

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/simmap.git
   cd simmap
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Run tests to verify setup:
   ```bash
   make test
   ```

## Development Workflow

### Using the Makefile

The project includes a Makefile for common tasks. See all available commands:

```bash
make help
```

Common commands:
- `make test` - Run all tests
- `make test-file FILE=tests/Unit/MapperTest.php` - Run specific test file
- `make test-filter FILTER=testMethodName` - Run specific test method
- `make test-coverage` - Run tests with coverage report
- `make example` - Run the basic usage example
- `make clean` - Clean cache and temporary files

### Making Changes

1. Create a feature branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes following our coding standards

3. Add or update tests for your changes

4. Ensure all tests pass:
   ```bash
   make test
   ```

5. Commit your changes with clear, descriptive messages:
   ```bash
   git commit -m "Add feature: brief description"
   ```

### Coding Standards

- Follow PSR-12 coding standards
- Use strict types (`declare(strict_types=1);`)
- Write type hints for all parameters and return types
- Use PHP 8.1+ features where appropriate
- Write clear, self-documenting code
- Add PHPDoc blocks for complex methods

### Testing Requirements

- All new features must include unit tests
- Maintain or improve code coverage
- Tests should be in the `tests/Unit/` directory
- Use descriptive test method names
- Follow the Arrange-Act-Assert pattern

Example test structure:
```php
public function testMapperMapsPropertiesCorrectly(): void
{
    // Arrange
    $source = new SourceClass();
    $source->property = 'value';

    // Act
    $result = $this->mapper->map($source, TargetClass::class);

    // Assert
    $this->assertSame('value', $result->property);
}
```

### Documentation

- Update README.md if adding new features or changing behavior
- Add examples for new functionality
- Update CHANGELOG.md following [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format
- Include PHPDoc comments for public APIs

## Pull Request Process

1. Update documentation reflecting your changes
2. Add entry to CHANGELOG.md under `[Unreleased]` section
3. Ensure all tests pass and code follows standards
4. Push to your fork and create a Pull Request
5. Fill in the PR template with:
   - Description of changes
   - Motivation and context
   - Type of change (bugfix, feature, breaking change)
   - Testing performed
   - Related issues

### PR Guidelines

- Keep PRs focused on a single feature or fix
- Include relevant tests
- Update documentation as needed
- Respond to review feedback promptly
- Squash commits if requested

## Reporting Bugs

When reporting bugs, please include:

- PHP version
- Symfony PropertyAccess version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Code sample demonstrating the issue
- Stack trace if applicable

Use the GitHub issue tracker and apply the "bug" label.

## Feature Requests

We welcome feature requests! Please:

- Check if the feature already exists or is planned
- Describe the use case and benefits
- Provide examples of how it would work
- Consider submitting a PR if you can implement it

## Questions and Support

- Check existing documentation first
- Search closed issues for similar questions
- Open a new issue with the "question" label
- Be specific and provide context

## Release Process

Maintainers will:

1. Review and merge PRs
2. Update version numbers following SemVer
3. Update CHANGELOG.md with release date
4. Create GitHub release with notes
5. Publish to Packagist

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

## Recognition

Contributors will be recognized in:
- GitHub contributors list
- Release notes for significant contributions
- Special thanks in documentation

Thank you for contributing to Simmap!
