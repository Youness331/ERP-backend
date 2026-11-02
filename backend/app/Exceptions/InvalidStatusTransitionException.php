<?php

namespace App\Exceptions;

class InvalidStatusTransitionException extends BusinessException
{
    public static function from(string $document, string $current, string $action): self
    {
        return new self(sprintf(
            'Impossible de %s un(e) %s au statut « %s ».',
            $action,
            $document,
            $current,
        ));
    }
}
