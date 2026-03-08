<?php
if (!defined('ABSPATH')) exit;

/**
 * Crypto fallback for when ETT Price Helper (and its ETT_Crypto class) is not active.
 *
 * Identical algorithm and key derivation to ETT_Crypto — secrets encrypted here
 * are directly readable by ETT_Crypto and vice versa, so installing Price Helper
 * later requires no migration of stored values.
 *
 * All code in this plugin uses ETT_EL_CryptoActive as a single consistent reference,
 * regardless of which underlying class is actually available.
 */
if (!class_exists('ETT_Crypto')) {

    class ETT_EL_Crypto {

        private static function enc_key(): string {
            return hash('sha256', AUTH_KEY . SECURE_AUTH_KEY, true);
        }

        private static function mac_key(): string {
            return hash('sha256', SECURE_AUTH_KEY . AUTH_KEY, true);
        }

        /** @return array{ciphertext:string,iv:string,mac:string} */
        public static function encrypt_triplet(string $plaintext): array {
            if ($plaintext === '') return ['ciphertext' => '', 'iv' => '', 'mac' => ''];

            $iv = random_bytes(16);

            $cipher_raw = openssl_encrypt(
                $plaintext,
                'AES-256-CBC',
                self::enc_key(),
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($cipher_raw === false) {
                throw new Exception('Encryption failed');
            }

            $mac_raw = hash_hmac('sha256', $iv . $cipher_raw, self::mac_key(), true);

            return [
                'ciphertext' => base64_encode($cipher_raw),
                'iv'         => base64_encode($iv),
                'mac'        => base64_encode($mac_raw),
            ];
        }

        public static function decrypt_triplet(string $ciphertext, string $iv_b64, string $mac_b64): string {
            if ($ciphertext === '') return '';

            $iv = base64_decode($iv_b64, true);
            if ($iv === false || strlen($iv) !== 16) return '';

            $cipher_raw = base64_decode($ciphertext, true);
            $mac_raw    = base64_decode($mac_b64, true);
            if ($cipher_raw === false || $mac_raw === false) return '';

            $calc = hash_hmac('sha256', $iv . $cipher_raw, self::mac_key(), true);
            if (!hash_equals($calc, $mac_raw)) return '';

            $plain = openssl_decrypt(
                $cipher_raw,
                'AES-256-CBC',
                self::enc_key(),
                OPENSSL_RAW_DATA,
                $iv
            );

            return $plain === false ? '' : (string) $plain;
        }
    }

    class_alias('ETT_EL_Crypto', 'ETT_EL_CryptoActive');

} else {

    // Price Helper is active — alias ETT_Crypto so all code uses one reference.
    class_alias('ETT_Crypto', 'ETT_EL_CryptoActive');

}
