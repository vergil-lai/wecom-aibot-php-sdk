<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

/**
 * AES-256-CBC 文件解密
 */
class Crypto
{
    /**
     * 解密文件内容
     *
     * @param string $encryptedData 加密后的数据
     * @param string $aesKey Base64 编码的 AES-256 密钥
     * @return string 解密后的原始数据
     */
    public static function decryptFile(string $encryptedData, string $aesKey): string
    {
        if ($encryptedData === '' || $encryptedData === null) {
            throw new \InvalidArgumentException('decryptFile: encryptedData is empty or not provided');
        }

        if ($aesKey === '' || $aesKey === null) {
            throw new \InvalidArgumentException('decryptFile: aesKey must be a non-empty string');
        }

        // 将 Base64 编码的 aesKey 解码为二进制
        $key = base64_decode($aesKey, true);
        if ($key === false) {
            throw new \InvalidArgumentException('decryptFile: aesKey is not valid base64');
        }

        // IV 取 aesKey 解码后的前 16 字节
        $iv = substr($key, 0, 16);

        // 将加密数据从 Base64 解码
        $encryptedBuffer = base64_decode($encryptedData, true);
        if ($encryptedBuffer === false) {
            throw new \InvalidArgumentException('decryptFile: encryptedData is not valid base64');
        }

        try {
            // 使用 aes-256-cbc 解密，关闭自动 padding
            $decrypted = openssl_decrypt(
                $encryptedBuffer,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,  // 不自动去除 padding
                $iv
            );

            if ($decrypted === false) {
                throw new \RuntimeException('OpenSSL decryption failed');
            }

            // 手动去除 PKCS#7 填充（支持 32 字节 block）
            return self::unpad($decrypted);
        } catch (\Throwable $e) {
            throw new \RuntimeException('decryptFile: Decryption failed - ' . $e->getMessage());
        }
    }

    /**
     * 移除 PKCS#7 填充
     *
     * @param string $data解密后的数据
     * @return string 去除 padding 后的数据
     */
    private static function unpad(string $data): string
    {
        if ($data === '') {
            return $data;
        }

        $padLen = ord($data[strlen($data) - 1]);

        // 验证 padding 长度有效
        if ($padLen < 1 || $padLen > 32 || $padLen > strlen($data)) {
            throw new \RuntimeException("Invalid PKCS#7 padding value: {$padLen}");
        }

        // 验证所有 padding 字节是否一致
        for ($i = strlen($data) - $padLen; $i < strlen($data); $i++) {
            if (ord($data[$i]) !== $padLen) {
                throw new \RuntimeException('Invalid PKCS#7 padding: padding bytes mismatch');
            }
        }

        return substr($data, 0, strlen($data) - $padLen);
    }
}
