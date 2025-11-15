# Security Policy

## Supported Versions

We release security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

We take security seriously. If you discover a security vulnerability in Simmap, please report it responsibly.

### How to Report

**Please DO NOT open a public GitHub issue for security vulnerabilities.**

Instead, report security issues by:

1. **Email**: Send details to the maintainer (contact information in composer.json)
2. **Subject**: Include "SECURITY" in the subject line
3. **Details**: Provide as much information as possible:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if available)

### What to Expect

- **Acknowledgment**: Within 48 hours of your report
- **Initial Assessment**: Within 7 days
- **Resolution Timeline**: Depends on severity
  - Critical: Within 7 days
  - High: Within 30 days
  - Medium/Low: Within 90 days

### Disclosure Policy

- We will coordinate disclosure with you
- Security fixes will be released as soon as possible
- Credit will be given to reporters (unless you prefer anonymity)
- CVE IDs will be requested for significant vulnerabilities

## Security Considerations

### Safe Usage

Simmap is designed for **internal data mapping** between trusted objects. Please be aware:

1. **Input Validation**: Always validate and sanitize external input **before** mapping
2. **Trusted Sources**: Only map data from trusted sources
3. **Property Access**: The mapper uses Symfony PropertyAccess, which has its own security considerations

### Potential Risks

While Simmap itself doesn't execute arbitrary code, be mindful of:

- **Property Injection**: Mapping untrusted data to sensitive properties
- **Nested Paths**: Deep nesting could potentially access unintended properties
- **Reflection**: The library uses PHP Reflection API for metadata reading

### Best Practices

```php
// ❌ DON'T: Map untrusted external input directly
$dto = $mapper->map($_POST, UserDTO::class);

// ✅ DO: Validate and sanitize first
$validatedData = $validator->validate($_POST);
$dto = new UserDTO();
$dto->name = $validatedData['name'];
$dto->email = $validatedData['email'];
$entity = $mapper->map($dto, UserEntity::class);
```

### Dependency Security

We rely on:

- **Symfony PropertyAccess**: Regularly updated dependency
- **PHP**: Requires 8.1+ for security features

Keep dependencies updated:
```bash
composer update
```

## Security Updates

Security patches will be:

1. Released as patch versions (e.g., 1.0.1)
2. Documented in CHANGELOG.md with `[SECURITY]` tag
3. Announced in release notes
4. Tagged with security advisory if severe

## Vulnerability History

No vulnerabilities reported to date.

## Additional Resources

- [Symfony Security](https://symfony.com/doc/current/security.html)
- [PHP Security](https://www.php.net/manual/en/security.php)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)

## Contact

For security-related questions (not vulnerabilities):
- Open a GitHub issue with `[security]` prefix
- Check existing security documentation

Thank you for helping keep Simmap and its users safe!
