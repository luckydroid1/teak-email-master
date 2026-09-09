<?php
/**
 * otp.php — OTP code extraction from email content.
 * Hanya memproses BODY (header di-strip) supaya tanggal/kode header tidak nyasar.
 * Prioritas: keyword-context → standalone digit line → digit acak.
 * Fallback: return full text supaya AI agent bisa parse sendiri.
 */
declare(strict_types=1);

const OTP_PATTERN = '/\b(\d{4,8})\b/';

const OTP_KEYWORDS = [
    'code', 'otp', 'one-time', 'onetime', 'verification', 'verify',
    'pin', 'passcode', 'security code', 'confirmation code', 'login code',
];

/**
 * @param string $raw Output doveadm (text.utf8: + headers + body)
 * @return array {otp: ?string, text: string}
 */
function extract_otp(string $raw): array {
    $text = normalize_email_text($raw);
    $lines = preg_split('/\r?\n/', $text);

    // 1. Cari baris dengan keyword OTP + digit
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        foreach (OTP_KEYWORDS as $kw) {
            if (stripos($line, $kw) !== false) {
                if (preg_match(OTP_PATTERN, $line, $m)) {
                    return ['otp' => $m[1], 'text' => $text];
                }
                break; // keyword ketemu di baris ini, cek digit; kalau ga ada lanjut baris lain
            }
        }
    }

    // 2. Lines that are purely digits (OTPs are often sent separately)
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^(\d{4,8})$/', $line, $m)) {
            return ['otp' => $m[1], 'text' => $text];
        }
    }

    // 3. Digit acak pertama di body
    if (preg_match(OTP_PATTERN, $text, $m)) {
        return ['otp' => $m[1], 'text' => $text];
    }

    return ['otp' => null, 'text' => $text];
}

/**
 * Clean doveadm output → BODY text only.
 * - Potong mulai marker "text.utf8:"
 * - Potong header (sampai baris kosong pertama)
 * - Decode quoted-printable, strip HTML tags
 */
function normalize_email_text(string $raw): string {
    $t = $raw;

    // Ambil mulai dari marker body doveadm
    $pos = stripos($t, 'text.utf8:');
    if ($pos !== false) {
        $t = substr($t, $pos + strlen('text.utf8:'));
    }

    // Potong header block (header diakhiri baris kosong pertama)
    $pos2 = strpos($t, "\n\n");
    if ($pos2 !== false) {
        $t = substr($t, $pos2 + 2);
    }

    // Decode MIME quoted-printable
    $t = quoted_printable_decode($t);

    // Strip HTML, pertahankan newline
    $t = preg_replace('/<br\s*\/?>/i', "\n", $t) ?? $t;
    $t = preg_replace('/<\/p>/i', "\n", $t) ?? $t;
    $t = preg_replace('/<[^>]+>/', '', $t) ?? $t;
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Normalisasi whitespace
    $t = preg_replace('/[ \t]+/', ' ', $t) ?? $t;
    return trim($t);
}
