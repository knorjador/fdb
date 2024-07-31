<?php

namespace App\Service;

class CryptoService
{
    
    private string $key;
    private string $cipherMethod = 'aes-256-cbc';

    /**
     * Constructor CryptoService
     *
     * @param string $key The encryption key
    */
    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * Encrypts the given cipherMethod
     *
     * @param string $data
     * @return string The base64-encoded encrypted data
    */
    public function encrypt(string $data): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipherMethod));
        $encrypted = openssl_encrypt($data, $this->cipherMethod, $this->key, 0, $iv);

        if ($encrypted === false) {
           return false;
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypts the given base64-encoded encrypted data
     *
     * @param string $data
     * @return string|null The decrypted data or null if decryption fails
    */
    public function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length($this->cipherMethod);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        $decrypted = openssl_decrypt($encrypted, $this->cipherMethod, $this->key, 0, $iv);

        return $decrypted;
    }

}

