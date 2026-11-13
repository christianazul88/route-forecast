<?php
declare(strict_types=1);

final class RoutingException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 502)
    {
        parent::__construct($message);
    }
}
