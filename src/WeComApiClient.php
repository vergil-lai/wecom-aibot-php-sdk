<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
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
     * @param string $url 下载地址
     * @param string|null $aesKey 解密密钥（可选，不传则返回原始数据）
     * @return PromiseInterface<DownloadFileResult>
     */
    public function downloadFileAsync(string $url, ?string $aesKey = null): PromiseInterface
    {
        return $this->httpClient->get($url)->then(
            function (ResponseInterface $response) use ($aesKey) {
                $contentDisposition = $response->getHeaderLine('Content-Disposition');
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
                    );
                }

                // 解密
                $decrypted = Crypto::decryptFile($body, $aesKey);

                return new DownloadFileResult(
                    buffer: $decrypted,
                    filename: $filename,
                );
            }
        );
    }

    /**
     * 下载并解密文件（同步版本，内部使用）
     *
     * @param string $url 下载地址
     * @param string|null $aesKey 解密密钥（可选）
     * @return DownloadFileResult
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

}
