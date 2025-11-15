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
}
