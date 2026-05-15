<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use VergilLai\WecomAiBot\Types\DownloadFileResult;

/**
 * 企业微信 API 客户端（ReactPHP HTTP）
 */
class WeComApiClient
{
    private Browser $httpClient;

    public function __construct(
        private readonly int $requestTimeout = 10000,
    ) {
        $this->httpClient = new Browser(null, Loop::get());
    }

    /**
     * 下载并解密文件（异步）
     *
     * @param  string  $url  下载地址
     * @param  string|null  $aesKey  解密密钥（可选，不传则返回原始数据）
     * @return PromiseInterface<DownloadFileResult>
     */
    public function downloadFileAsync(string $url, ?string $aesKey = null): PromiseInterface
    {
        return $this->httpClient->get($url)->then(
            function (ResponseInterface $response) use ($aesKey) {
                $contentDisposition = $response->getHeaderLine('Content-Disposition');
                $contentType = $response->getHeaderLine('Content-Type');
                $body = (string) $response->getBody();

                // 提取文件名
                $filename = null;
                if ($contentDisposition) {
                    $filename = $this->extractFilename($contentDisposition);
                }

                // 如果没有提供 aesKey，直接返回原始数据
                if ($aesKey === null || $aesKey === '') {
                    return new DownloadFileResult(
                        buffer: $body,
                        filename: $filename,
                        decrypted: false,
                    );
                }

                // 解密；如果 COS 预签名 URL 已返回明文，只在能识别为真实文件时回退。
                try {
                    $decrypted = Crypto::decryptFile($body, $aesKey);
                    return new DownloadFileResult(
                        buffer: $decrypted,
                        filename: $filename,
                        decrypted: true,
                    );
                } catch (\RuntimeException $e) {
                    if ($this->isProbablyPlainFile($body, $filename, $contentType)) {
                        return new DownloadFileResult(
                            buffer: $body,
                            filename: $filename,
                            decrypted: false,
                        );
                    }

                    throw $e;
                }
            }
        );
    }

    /**
     * 下载并解密文件（同步版本，内部使用）
     *
     * @param  string  $url  下载地址
     * @param  string|null  $aesKey  解密密钥（可选）
     */
    public function downloadFile(string $url, ?string $aesKey = null): DownloadFileResult
    {
        $loop = Loop::get();
        $result = null;
        $error = null;

        $this->downloadFileAsync($url, $aesKey)->then(
            function ($r) use (&$result, $loop) {
                $result = $r;
                $loop->stop();
            },
            function ($e) use (&$error, $loop) {
                $error = $e;
                $loop->stop();
            }
        );

        $timedOut = false;
        $timer = $loop->addTimer($this->requestTimeout / 1000, function () use ($loop, &$timedOut) {
            $timedOut = true;
            $loop->stop();
        });

        $loop->run();
        $loop->cancelTimer($timer);

        if ($error !== null) {
            throw $error;
        }

        if ($timedOut || $result === null) {
            throw new \RuntimeException('Download timeout');
        }

        return $result;
    }

    /**
     * 从 Content-Disposition 头提取文件名
     * 优先匹配 filename*=UTF-8''xxx 格式（RFC 5987），其次匹配 filename="xxx" 或 filename=xxx 格式
     */
    private function extractFilename(string $contentDisposition): ?string
    {
        // 优先匹配 filename*=UTF-8''xxx 格式（RFC 5987）
        if (preg_match("/filename\*=UTF-8''([^;\s]+)/i", $contentDisposition, $matches)) {
            return rawurldecode($matches[1]);
        }

        // 匹配 filename="xxx" 或 filename=xxx 格式
        if (preg_match('/filename="?([^";\s]+)"?/i', $contentDisposition, $matches)) {
            return rawurldecode($matches[1]);
        }

        return null;
    }

    private function isProbablyPlainFile(string $body, ?string $filename, string $contentType): bool
    {
        if ($body === '') {
            return false;
        }

        $extension = strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION));
        $header = substr($body, 0, 16);

        if (str_starts_with($header, "PK\x03\x04")) {
            return in_array($extension, ['docx', 'pptx', 'xlsx', 'zip'], true);
        }

        if (str_starts_with($header, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return in_array($extension, ['doc', 'ppt', 'xls'], true);
        }

        if (str_starts_with($header, '%PDF-')) {
            return $extension === 'pdf';
        }

        if (str_starts_with($header, "\x89PNG\r\n\x1A\n")) {
            return $extension === 'png';
        }

        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return in_array($extension, ['jpg', 'jpeg'], true);
        }

        if (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')) {
            return $extension === 'gif';
        }

        if (str_starts_with($header, 'RIFF') && substr($body, 8, 4) === 'WEBP') {
            return $extension === 'webp';
        }

        if (in_array($extension, ['csv', 'txt', 'md', 'json'], true)) {
            return ! str_contains(substr($body, 0, 4096), "\x00");
        }

        return str_starts_with(strtolower($contentType), 'text/')
            && ! str_contains(substr($body, 0, 4096), "\x00");
    }
}
