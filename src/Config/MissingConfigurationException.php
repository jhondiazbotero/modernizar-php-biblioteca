<?php

declare(strict_types=1);

namespace Library\Config;

use RuntimeException;

/** A required environment variable is absent or empty. The message names the variable, never a value. */
final class MissingConfigurationException extends RuntimeException
{
    public static function forVariable(string $name): self
    {
        return new self(sprintf('Missing required environment variable %s.', $name));
    }
}
