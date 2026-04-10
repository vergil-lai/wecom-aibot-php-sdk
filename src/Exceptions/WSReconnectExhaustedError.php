<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Exceptions;

use Exception;

/**
 * 重连耗尽异常
 */
class WSReconnectExhaustedError extends Exception
{
    public const string CODE = 'WS_RECONNECT_EXHAUSTED';

    public function __construct(
        public readonly int $attempt,
        string $message = 'WS reconnect exhausted',
    ) {
        parent::__construct($message);
    }
}
