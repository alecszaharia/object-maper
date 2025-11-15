<?php

namespace Alecszaharia\Simmap\Exception;

use Exception;

/**
 * Exception thrown when mapping operations fail.
 */
class MappingException extends Exception
{
    public static function cannotCreateInstance(string $className, string $reason): self
    {
        return new self(
            sprintf('Cannot create instance of class "%s": %s', $className, $reason)
        );
    }

    public static function invalidTargetType(mixed $target): self
    {
        $type = is_object($target) ? get_class($target) : gettype($target);
        return new self(
            sprintf('Invalid target type "%s". Target must be an object instance, a class name string, or null.', $type)
        );
    }

    public static function propertyAccessError(string $class, string $property, string $reason): self
    {
        return new self(
            sprintf('Cannot access property "%s" on class "%s": %s', $property, $class, $reason)
        );
    }

    public static function notMappable(string $className, string $role): self
    {
        return new self(
            sprintf(
                'Class "%s" cannot be used as %s for mapping. ' .
                'Add #[Mappable] attribute to the class to enable mapping.',
                $className,
                $role
            )
        );
    }

    public static function arrayMappingError(string $class, string $property, string $reason): self
    {
        return new self(
            sprintf('Error mapping array property "%s" on class "%s": %s', $property, $class, $reason)
        );
    }

    public static function missingArrayTargetClass(string $class, string $property): self
    {
        return new self(
            sprintf(
                'Array property "%s" on class "%s" requires #[MapArray] attribute with target class. ' .
                'Use #[MapArray(TargetClass::class)] to specify the element type.',
                $property,
                $class
            )
        );
    }
}
