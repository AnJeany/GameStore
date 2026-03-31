<?php
// ============================================
//  config/jwt.php
//  JWT helper — ký và verify token thủ công
//  (không dùng thư viện ngoài để học rõ hơn)
// ============================================

class JWT {
    // ⚠️ Đổi secret này trước khi deploy thật
    private static string $secret = 'GAMESTORE_JWT_SECRET_KEY_2024!';
    private static int    $expire = 86400; // 24 giờ (giây)

    // ---------- Tạo token ----------
    public static function generate(array $payload): string {
        $header = self::base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]));

        $payload['iat'] = time();
        $payload['exp'] = time() + self::$expire;

        $encodedPayload = self::base64UrlEncode(json_encode($payload));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$encodedPayload", self::$secret, true)
        );

        return "$header.$encodedPayload.$signature";
    }

    // ---------- Verify & decode token ----------
    public static function verify(string $token): array|false {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        [$header, $payload, $signature] = $parts;

        // Kiểm tra chữ ký
        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", self::$secret, true)
        );
        if (!hash_equals($expectedSig, $signature)) return false;

        // Decode payload
        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!$data) return false;

        // Kiểm tra hết hạn
        if (isset($data['exp']) && $data['exp'] < time()) return false;

        return $data;
    }

    // ---------- Helpers ----------
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
