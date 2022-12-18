<?php

declare(strict_types=1);

namespace RandExp;

use Exception;
use Throwable;

class RegexException extends Exception
{
    public function __construct(string $regex, string $message, ?Throwable $previous = null)
    {
        parent::__construct("Invalid regular expression: /$regex/: $message", 1, $previous);
    }
}
