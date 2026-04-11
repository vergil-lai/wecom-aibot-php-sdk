<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

use Psr\Log\LoggerInterface;

/**
 * WSClient 配置选项
 */
readonly class WsClientOptions
{
    public function __construct(
        public string $botId,
        public string $secret,
        public ?int $scene = null,
        public ?string $plugVersion = null,
        public int $reconnectInterval = 1000,
        public int $maxReconnectAttempts = 10,
        public int $maxAuthFailureAttempts = 5,
        public int $heartbeatInterval = 30000,
        public int $requestTimeout = 10000,
        public string $wsUrl = 'wss://openws.work.weixin.qq.com',
        public int $maxReplyQueueSize = 500,
        public ?LoggerInterface $logger = null,
    ) {}
}
