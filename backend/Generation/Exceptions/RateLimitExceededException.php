<?php

namespace App\Generation\Exceptions;

class RateLimitExceededException extends GenerationException
{
    public function __construct(string $message = 'Daily generation limit reached.')
    {
        parent::__construct($message);
    }
}
