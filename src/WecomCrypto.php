<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

/**
 * WeCom 加解密通用核心
 *
 * 独立于 Webhook、WebSocket、Agent 的具体协议形态，统一提供基于 AES-256-CBC
 * 的加解密与 SHA1 签名计算能力。
 */
class WecomCrypto
{
    private const PKCS7_BLOCK_SIZE = 32;
    private const AES_KEY_LENGTH = 32;

    private string $aesKey;
    private string $iv;

    public function __construct(
        private readonly string $token,
        string $encodingAESKey,
        private readonly ?string $receiveId = null, // 对应企业微信的 corpId 或 botId (用于校验与追加)
    ) {
        if ($token === '') {
            throw new \InvalidArgumentException('token is required');
        }
        $this->aesKey = self::decodeEncodingAESKey($encodingAESKey);
        $this->iv = substr($this->aesKey, 0, 16);
    }

    /**
     * 解码企业微信提供的 Base64 encodingAESKey
     */
    public static function decodeEncodingAESKey(string $encodingAESKey): string
    {
        $trimmed = trim($encodingAESKey);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('encodingAESKey missing');
        }
        $withPadding = str_ends_with($trimmed, '=') ? $trimmed : $trimmed . '=';
        $key = base64_decode($withPadding, true);
        if ($key === false) {
            throw new \InvalidArgumentException('encodingAESKey is not valid base64');
        }
        if (strlen($key) !== self::AES_KEY_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'invalid encodingAESKey (expected %d bytes, got %d)',
                self::AES_KEY_LENGTH,
                strlen($key)
            ));
        }
        return $key;
    }

    /**
     * PKCS#7 填充
     */
    public static function pkcs7Pad(string $buf, int $blockSize = self::PKCS7_BLOCK_SIZE): string
    {
        $mod = strlen($buf) % $blockSize;
        $pad = $mod === 0 ? $blockSize : $blockSize - $mod;
        return $buf . str_repeat(chr($pad), $pad);
    }

    /**
     * PKCS#7 解除填充
     */
    public static function pkcs7Unpad(string $buf, int $blockSize = self::PKCS7_BLOCK_SIZE): string
    {
        if (strlen($buf) === 0) {
            throw new \RuntimeException('invalid pkcs7 payload');
        }
        $pad = ord($buf[strlen($buf) - 1]);
        if ($pad < 1 || $pad > $blockSize) {
            throw new \RuntimeException('invalid pkcs7 padding value');
        }
        if ($pad > strlen($buf)) {
            throw new \RuntimeException('invalid pkcs7 payload length');
        }
        for ($i = 0; $i < $pad; $i++) {
            if (ord($buf[strlen($buf) - 1 - $i]) !== $pad) {
                throw new \RuntimeException('invalid pkcs7 padding byte');
            }
        }
        return substr($buf, 0, strlen($buf) - $pad);
    }

    /**
     * 计算 SHA1 哈希
     */
    private static function sha1Hex(string $input): string
    {
        return sha1($input);
    }

    /**
     * 计算 WeCom 消息签名
     */
    public function computeSignature(string $timestamp, string $nonce, string $encrypt): string
    {
        $parts = array_map('strval', [$this->token, $timestamp, $nonce, $encrypt]);
        sort($parts, SORT_STRING);
        return self::sha1Hex(implode('', $parts));
    }

    /**
     * 验证 WeCom 消息签名
     */
    public function verifySignature(string $signature, string $timestamp, string $nonce, string $encrypt): bool
    {
        $expected = $this->computeSignature($timestamp, $nonce, $encrypt);
        return $expected === $signature;
    }

    /**
     * 消息解密
     *
     * 返回纯文本字符串（XML 或 JSON 根据上层业务而定）
     */
    public function decrypt(string $encryptText): string
    {
        $decryptedPadded = openssl_decrypt(
            $encryptText,
            'aes-256-cbc',
            $this->aesKey,
            OPENSSL_RAW_DATA,
            $this->iv
        );
        if ($decryptedPadded === false) {
            throw new \RuntimeException('OpenSSL decryption failed');
        }

        $decrypted = self::pkcs7Unpad($decryptedPadded, self::PKCS7_BLOCK_SIZE);

        if (strlen($decrypted) < 20) {
            throw new \RuntimeException(sprintf('invalid payload (expected >=20 bytes, got %d)', strlen($decrypted)));
        }

        // 16 bytes random + 4 bytes length + msg + receiveId
        $msgLen = unpack('N', substr($decrypted, 16, 4))[1];
        $msgStart = 20;
        $msgEnd = $msgStart + $msgLen;
        if ($msgEnd > strlen($decrypted)) {
            throw new \RuntimeException(sprintf('invalid msg length (msgEnd=%d, total=%d)', $msgEnd, strlen($decrypted)));
        }
        $msg = substr($decrypted, $msgStart, $msgEnd);

        $receiveId = $this->receiveId ?? '';
        if ($receiveId !== '') {
            $trailing = substr($decrypted, $msgEnd);
            if ($trailing !== $receiveId) {
                throw new \RuntimeException(sprintf('receiveId mismatch (expected "%s", got "%s")', $receiveId, $trailing));
            }
        }

        return $msg;
    }

    /**
     * 消息加密
     *
     * 加密明文并返回 base64 格式密文与对应的新签名
     *
     * @return array{encrypt: string, signature: string}
     */
    public function encrypt(string $plainText, string $timestamp, string $nonce): array
    {
        $random16 = self::randomBytes(16);
        $msgLen = pack('N', strlen($plainText));
        $receiveIdBuf = $this->receiveId ?? '';

        $raw = $random16 . $msgLen . ($plainText ?: '') . $receiveIdBuf;
        $padded = self::pkcs7Pad($raw, self::PKCS7_BLOCK_SIZE);

        $encryptedBuf = openssl_encrypt(
            $padded,
            'aes-256-cbc',
            $this->aesKey,
            OPENSSL_RAW_DATA,
            $this->iv
        );
        if ($encryptedBuf === false) {
            throw new \RuntimeException('OpenSSL encryption failed');
        }

        $encryptBase64 = base64_encode($encryptedBuf);
        $signature = $this->computeSignature($timestamp, $nonce, $encryptBase64);

        return ['encrypt' => $encryptBase64, 'signature' => $signature];
    }

    /**
     * 生成指定长度的随机字节
     */
    private static function randomBytes(int $length): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= chr(mt_rand(0, 255));
        }
        return $result;
    }
}
