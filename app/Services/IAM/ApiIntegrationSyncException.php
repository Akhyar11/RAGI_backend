<?php

namespace App\Services\IAM;

use RuntimeException;

class ApiIntegrationSyncException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 422)
    {
        parent::__construct($message);
    }
}