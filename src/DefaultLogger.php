<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

/**
 * 默认日志实现
 */
class DefaultLogger implements LoggerInterface
{
    public function debug(string $message, mixed ...$args): void
    {
        $this->log('DEBUG', $message, $args);
    }

    public function info(string $message, mixed ...$args): void
    {
        $this->log('INFO', $message, $args);
    }

    public function warn(string $message, mixed ...$args): void
    {
        $this->log('WARN', $message, $args);
    }

    public function error(string $message, mixed ...$args): void
    {
        $this->log('ERROR', $message, $args);
    }

    private function log(string $level, string $message, array $args): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = $args ? vsprintf($message, $args) : $message;
        echo "[{$timestamp}] [{$level}] {$formatted}" . PHP_EOL;
    }
}
