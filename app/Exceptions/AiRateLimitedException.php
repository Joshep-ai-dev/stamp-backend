<?php

namespace App\Exceptions;

use RuntimeException;

class AiRateLimitedException extends RuntimeException
{
    public function __construct(public int $retryAt)
    {
        parent::__construct('OpenAI rate limit reached. Waiting before automatic retry.');
    }
}
