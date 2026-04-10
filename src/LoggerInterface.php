<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

/**
 * 日志接口
 */
interface LoggerInterface
{
    public function debug(string $message, mixed ...$args): void;
    public function info(string $message, mixed ...$args): void;
    public function warn(string $message, mixed ...$args): void;
    public function error(string $message, mixed ...$args): void;
}
