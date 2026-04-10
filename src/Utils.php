<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

/**
 * 工具函数
 */
class Utils
{
    /**
     * 生成随机十六进制字符串
     *
     * @param int $length 随机字符串长度，默认 8
     * @return string
     */
    public static function generateRandomString(int $length = 8): string
    {
        $bytes = random_bytes((int) ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }

    /**
     * 生成唯一请求 ID
     *
     * 格式：{prefix}_{timestamp}_{random}
     *
     * @param string $prefix 前缀，通常为 cmd 名称
     * @return string
     */
    public static function generateReqId(string $prefix = 'req'): string
    {
        $timestamp = (int) (microtime(true) * 1000);
        $random = self::generateRandomString();
        return "{$prefix}_{$timestamp}_{$random}";
    }

    /**
     * 计算分片数量
     *
     * @param int $fileSize 文件大小
     * @param int $chunkSize 分片大小（字节）
     * @return int 分片数量
     */
    public static function calculateChunkCount(int $fileSize, int $chunkSize = 512 * 1024): int
    {
        return (int) ceil($fileSize / $chunkSize);
    }
}
