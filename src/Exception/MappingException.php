<?php

declare(strict_types=1);

namespace Alecszaharia\Simmap\Exception;

use RuntimeException;

/**
 * Exception thrown when object mapping fails.
 */
final class MappingException extends RuntimeException
{
    /**
     * @param class-string $className
     */
    public static function notMappable(string $className): self
    {
        return new self(
            sprintf(
                'Class "%s" is not annotated with #[Mappable] attribute. ' .
                'Add #[Mappable(targetClass: TargetClass::class)] to enable mapping.',
                $className
            )
        );
    }

    /**
     * @param class-string $sourceClass
     * @param class-string $targetClass
     */
    public static function nonReciprocal(string $sourceClass, string $targetClass): self
    {
        return new self(
            sprintf(
                'Non-reciprocal mapping detected between "%s" and "%s". ' .
                'Both classes must have #[Mappable] attributes pointing to each other. ' .
                'Class "%s" does not declare "%s" as a mappable target.',
                $sourceClass,
                $targetClass,
                $targetClass,
                $sourceClass
            )
        );
    }

    /**
     * @param class-string $sourceClass
     * @param class-string $targetClass
     */
    public static function propertyAccessFailed(
        string $sourceClass,
        string $targetClass,
        string $propertyPath,
        string $reason
    ): self {
        return new self(
            sprintf(
                'Failed to access property "%s" during mapping from "%s" to "%s": %s',
                $propertyPath,
                $sourceClass,
                $targetClass,
                $reason
            )
        );
    }

    /**
     * @param class-string $className
     */
    public static function instantiationFailed(string $className, string $reason): self
    {
        return new self(
            sprintf(
                'Failed to instantiate target class "%s": %s',
                $className,
                $reason
            )
        );
    }

    public static function targetRequired(): self
    {
        return new self(
            'Target parameter is required. Provide either an object instance or a class name string.'
        );
    }

    /**
     * @param class-string $sourceClass
     * @param class-string $targetClass
     */
    public static function arrayMappingFailed(
        string $sourceClass,
        string $targetClass,
        string $propertyPath,
        int $itemIndex,
        string $reason
    ): self {
        return new self(
            sprintf(
                'Failed to map array item at index %d in property "%s" during mapping from "%s" to "%s": %s',
                $itemIndex,
                $propertyPath,
                $sourceClass,
                $targetClass,
                $reason
            )
        );
    }

    /**
     * @param class-string $sourceClass
     * @param class-string $targetClass
     */
    public static function arrayItemNotObject(
        string $sourceClass,
        string $targetClass,
        string $propertyPath,
        int $itemIndex,
        string $actualType
    ): self {
        return new self(
            sprintf(
                'Array item at index %d in property "%s" during mapping from "%s" to "%s" is not an object (got: %s). ' .
                'Only arrays of objects can be mapped.',
                $itemIndex,
                $propertyPath,
                $sourceClass,
                $targetClass,
                $actualType
            )
        );
    }

    /**
     * @param class-string $sourceClass
     * @param class-string $targetClass
     */
    public static function missingTargetClassForArray(
        string $sourceClass,
        string $targetClass,
        string $propertyPath
    ): self {
        return new self(
            sprintf(
                'Property "%s" during mapping from "%s" to "%s" is an array but no targetClass is specified. ' .
                'Add targetClass parameter to #[MapTo] attribute: #[MapTo(targetProperty: "...", targetClass: TargetClass::class)]',
                $propertyPath,
                $sourceClass,
                $targetClass
            )
        );
    }

    public static function circularReferenceDetected(string $className, string $propertyPath): self
    {
        return new self(
            sprintf(
                'Circular reference detected: class "%s" is already being mapped in the call stack. Property path: "%s"',
                $className,
                $propertyPath
            )
        );
    }
}
