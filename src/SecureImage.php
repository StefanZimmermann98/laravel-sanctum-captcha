<?php

namespace StefanZ\LaravelSanctumCaptcha;

class SecureImage
{

    private $m_secret = NULL;
    private $m_cipher = 'aes-128-gcm';

    private $m_width = 150;
    private $m_height = 50;
    private $m_image = NULL;
    private $m_background = [255, 255, 255, 127];
    private $m_font = "arial.ttf";
    private $m_size = 30;

    private $m_characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ23456789";
    private $m_length = 7;

    private $m_ciphertext = '';

    public function __construct(string|NULL $key = NULL)
    {

        $this->m_secret = $key;

        if (is_null($key)) {
            $this->m_secret = uniqid('LaravelSanctumCaptcha_', true);
        }
    }

    public function __destruct()
    {
        if ($this->m_image !== NULL) {
            imagedestroy($this->m_image);
        }
    }

    public function setCipher(string $cipher): \StefanZ\LaravelSanctumCaptcha\SecureImage
    {
        if (!in_array($cipher, openssl_get_cipher_methods())) {
            throw new \StefanZ\LaravelSanctumCaptcha\SecureImageException('Your defined cipher is not supported');
        }

        $this->m_cipher = $cipher;

        return $this;
    }

    public function setBackground(int $r, int $g, int $b, int $a): \StefanZ\LaravelSanctumCaptcha\SecureImage
    {
        $this->m_background = [$r, $g, $b, $a];

        return $this;
    }

    public function setLength(int $length): \StefanZ\LaravelSanctumCaptcha\SecureImage
    {
        $this->m_length = $length;

        return $this;
    }

    public function generateCaptcha(): array
    {
        $this->m_image = imagecreatetruecolor($this->m_width, $this->m_height);
        imagealphablending($this->m_image, true);
        imagesavealpha($this->m_image, true);

        $bg_color = imagecolorallocatealpha($this->m_image, $this->m_background[0], $this->m_background[1], $this->m_background[2], $this->m_background[3]);
        imagefill($this->m_image, 0, 0, $bg_color);


        $captcha_text = "";
        for ($i = 0; $i < $this->m_length; $i++) {
            $char = $this->m_characters[rand(0, strlen($this->m_characters) - 1)];
            $captcha_text .= $char;


            $color = imagecolorallocate($this->m_image, 0, 0, 0);
            imagettftext($this->m_image, $this->m_size, 0, 10 + $i * 30, 35, $color, $this->m_font, $char);
        }

        $ivlen = openssl_cipher_iv_length($this->m_cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($captcha_text, $this->m_cipher, $this->m_secret, $options = OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $this->m_secret, $as_binary = true);
        $this->m_ciphertext = base64_encode($iv . $hmac . $ciphertext_raw);
        ob_start();
        imagepng($this->m_image);
        $content = ob_get_contents();
        ob_end_clean();

        return [
            'cipher_text' => $this->m_ciphertext,
            'image_as_base64' => base64_encode($content)
        ];
    }

    public function getCipherText(): string
    {
        return $this->m_ciphertext;
    }

    public function as_png()
    {
        header("Content-Type: image/png");
        imagepng($this->m_image);
    }

    public function is_valid(string $input, string $ciphertext): bool
    {
        $c = base64_decode($ciphertext);
        $ivlen = openssl_cipher_iv_length($this->m_cipher);
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($c, $ivlen + $sha2len);
        $original_plaintext = openssl_decrypt($ciphertext_raw, $this->m_cipher, $this->m_secret, $options = OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $this->m_secret, $as_binary = true);
        if (hash_equals($hmac, $calcmac)) // Rechenzeitangriff-sicherer Vergleich
        {
            return $input === $ciphertext;
        }

        return FALSE;
    }
}