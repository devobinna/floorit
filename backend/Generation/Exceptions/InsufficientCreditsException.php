<?php

namespace App\Generation\Exceptions;

class InsufficientCreditsException extends GenerationException
{
    public function __construct(int $needed, int $available)
    {
        parent::__construct("Insufficient credits. Need {$needed}, have {$available}.");
    }
}
