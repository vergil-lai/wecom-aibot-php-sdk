<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Exceptions;

use Exception;

/**
 * 认证失败异常
 */
class WSAuthFailureError extends Exception
{
    public const string CODE = 'WS_AUTH_FAILURE_EXHAUSTED';

    public function __construct(
        public readonly int $errcode = 0,
        string $message = 'WS auth failed',
    ) {
        parent::__construct($message);
    }
}
