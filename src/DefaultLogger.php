<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

use Psr\Log\AbstractLogger;

/**
 * 默认日志实现
 */
class DefaultLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = $context ? vsprintf($message, $context) : $message;
        echo "[{$timestamp}] [{$level}] {$formatted}" . PHP_EOL;
    }
}
