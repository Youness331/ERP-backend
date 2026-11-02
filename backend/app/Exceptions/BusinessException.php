<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain rule violation. Rendered as a 422 JSON response by the API layer.
 */
class BusinessException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
