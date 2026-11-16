<?php

namespace App\Services;

final class TotpService
{
    public function generateSecret(int $length = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) $secret .= $alphabet[random_int(0, 31)];
        return $secret;
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) return false;
        $counter = intdiv($timestamp ?? time(), 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeAt($secret, $counter + $offset), $code)) return true;
        }
        return false;
    }

    public function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$account);
        return 'otpauth://totp/'.$label.'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    private function codeAt(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $number = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($number % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0; $bitsLeft = 0; $output = '';
        foreach (str_split(strtoupper(rtrim($input, '='))) as $char) {
            $value = strpos($alphabet, $char);
            if ($value === false) throw new \InvalidArgumentException('Invalid TOTP secret.');
            $buffer = (($buffer << 5) | $value) & 0xffffffff; $bitsLeft += 5;
            if ($bitsLeft >= 8) { $bitsLeft -= 8; $output .= chr(($buffer >> $bitsLeft) & 0xff); }
            $buffer &= (1 << $bitsLeft) - 1;
        }
        return $output;
    }
}
