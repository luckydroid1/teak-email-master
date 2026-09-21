<?php
/**
 * receipt.php — Parser struk, invoice, & billing email untuk otomatisasi finance.
 * Mengekstrak total amount, mata uang, nomor invoice, tanggal, vendor/merchant, dan status.
 */
declare(strict_types=1);

require_once __DIR__ . '/otp.php';

const RECEIPT_CURRENCY_SYMBOLS = [
    '$' => 'USD',
    '€' => 'EUR',
    '£' => 'GBP',
    '¥' => 'JPY',
    '₹' => 'INR',
    'RP' => 'IDR',
    'RP.' => 'IDR',
    'IDR' => 'IDR',
    'USD' => 'USD',
    'EUR' => 'EUR',
    'GBP' => 'GBP',
    'SGD' => 'SGD',
    'AUD' => 'AUD',
    'CAD' => 'CAD',
];

/**
 * Ekstrak data struk/receipt/invoice dari raw email body atau plain text.
 * @param string $raw Raw email content dari Dovecot/Mailcow
 * @return array{
 *   is_receipt: bool,
 *   vendor: ?string,
 *   amount: ?float,
 *   currency: ?string,
 *   invoice_number: ?string,
 *   date: ?string,
 *   summary: string,
 *   text: string
 * }
 */
function extract_receipt(string $raw): array {
    $text = normalize_email_text($raw);
    $lines = preg_split('/\r?\n/', $text);

    $is_receipt = false;
    $amount = null;
    $currency = 'USD';
    $invoice_no = null;
    $date = null;
    $vendor = null;

    // Filter email yang jelas-jelas hanya OTP/verifikasi/newsletter biasa
    if (preg_match('/^(Verify your |Your GitHub launch code|Updates to managing|Welcome to your)/i', $text)) {
        return [
            'is_receipt' => false,
            'vendor' => null,
            'amount' => null,
            'currency' => null,
            'invoice_number' => null,
            'date' => null,
            'summary' => 'Not a receipt email',
            'text' => $text
        ];
    }

    // 1. Ekstrak Total Amount & Mata Uang (Wajib ada nominal transaksi yang valid dan > 0)
    $amount_patterns = [
        '/(?:total(?: paid| due| amount)?|grand total|amount charged|subtotal|jumlah)\s*[:=]?\s*([A-Z]{3}|\$|€|£|¥|₹|Rp\.?)\s*([0-9\.,]+)/i',
        '/([A-Z]{3}|\$|€|£|¥|₹|Rp\.?)\s*([0-9\.,]+)\s*(?:total|paid|charged)/i',
        '/(?:paid|charged)\s*[:=]?\s*([A-Z]{3}|\$|€|£|¥|₹|Rp\.?)\s*([0-9\.,]+)/i'
    ];

    foreach ($amount_patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $raw_curr = strtoupper(trim($m[1]));
            $raw_amt = str_replace([',', ' '], ['', ''], trim($m[2]));
            if (substr_count($m[2], ',') === 1 && substr_count($m[2], '.') <= 1 && strpos($m[2], ',') > strpos($m[2], '.')) {
                $raw_amt = str_replace('.', '', $m[2]);
                $raw_amt = str_replace(',', '.', $raw_amt);
            }
            $val = (float)$raw_amt;
            if ($val > 0) {
                $currency = RECEIPT_CURRENCY_SYMBOLS[$raw_curr] ?? $raw_curr;
                $amount = $val;
                $is_receipt = true;
                break;
            }
        }
    }

    // Jika tidak ditemukan amount > 0, bukan receipt
    if (!$is_receipt || $amount === null || $amount <= 0) {
        return [
            'is_receipt' => false,
            'vendor' => null,
            'amount' => null,
            'currency' => null,
            'invoice_number' => null,
            'date' => null,
            'summary' => 'Not a receipt email',
            'text' => $text
        ];
    }

    // 2. Ekstrak Invoice / Order Number
    if (preg_match('/(?:invoice|order|receipt|faktur|transaksi|ref|reference)\s*(?:#|no\.?|id|code|number)?\s*[:#]\s*([a-z0-9\-_]{4,30})/i', $text, $m)) {
        $invoice_no = trim($m[1]);
    }

    // 3. Ekstrak Tanggal Transaksi
    if (preg_match('/(?:date|tanggal|issued|billed on)\s*[:]?\s*([0-9]{1,4}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{1,4}|[A-Za-z]{3,9}\s+[0-9]{1,2},?\s+[0-9]{4})/i', $text, $m)) {
        $date = trim($m[1]);
    }

    // 4. Ekstrak Vendor / Merchant
    foreach ($lines as $line) {
        $trim = trim($line);
        if (strlen($trim) > 2 && strlen($trim) < 40 && !preg_match('/^(hi|hello|dear|from:|to:|subject:|date:|--)/i', $trim)) {
            $vendor = $trim;
            break;
        }
    }

    $summary = sprintf(
        "Receipt: %s %s from %s (Ref: %s)",
        $currency,
        number_format((float)$amount, 2),
        $vendor ?? 'Unknown',
        $invoice_no ?? 'N/A'
    );

    return [
        'is_receipt' => true,
        'vendor' => $vendor,
        'amount' => $amount,
        'currency' => $currency,
        'invoice_number' => $invoice_no,
        'date' => $date,
        'summary' => $summary,
        'text' => $text,
    ];
}
