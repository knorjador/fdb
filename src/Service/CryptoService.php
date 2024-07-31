<?php

namespace App\Service;

class CryptoService
{
    
    private $key;
    private $cipherMethod = 'aes-256-cbc';

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function encrypt(string $data): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipherMethod));
        $encrypted = openssl_encrypt($data, $this->cipherMethod, $this->key, 0, $iv);

        if ($encrypted === false) {
           return false;
        }

        return base64_encode($iv . $encrypted);
    }

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

