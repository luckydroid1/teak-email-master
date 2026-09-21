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
 *   items: array,
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
    $items = [];

    $receipt_keywords = [
        'receipt', 'invoice', 'order confirmation', 'payment receipt',
        'bill', 'billing', 'statement', 'subscription renewed',
        'total paid', 'amount paid', 'struk', 'faktur', 'bukti pembayaran'
    ];

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') continue;
        foreach ($receipt_keywords as $kw) {
            if (stripos($trim, $kw) !== false) {
                $is_receipt = true;
                break 2;
            }
        }
    }

    // 1. Ekstrak Vendor / Merchant (biasanya di awal atau baris pertama yang bukan header)
    foreach ($lines as $line) {
        $trim = trim($line);
        if (strlen($trim) > 2 && strlen($trim) < 60 && !preg_match('/^(hi|hello|dear|from:|to:|subject:|date:)/i', $trim)) {
            $vendor = $trim;
            break;
        }
    }

    // 2. Ekstrak Invoice / Order Number
    // Contoh: Invoice #INV-2026-001, Order ID: 123456, Receipt No: RC-992
    if (preg_match('/(?:invoice|order|receipt|faktur|transaksi|ref|reference)\s*(?:#|no\.?|id|code|number)?\s*[:#]?\s*([a-z0-9\-_]{4,30})/i', $text, $m)) {
        $invoice_no = $m[1];
        $is_receipt = true;
    }

    // 3. Ekstrak Tanggal Transaksi
    if (preg_match('/(?:date|tanggal|issued|billed on)\s*[:]?\s*([0-9]{1,4}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{1,4}|[A-Za-z]{3,9}\s+[0-9]{1,2},?\s+[0-9]{4})/i', $text, $m)) {
        $date = $m[1];
    }

    // 4. Ekstrak Total Amount & Mata Uang
    // Cari baris yang mengandung 'Total', 'Amount Paid', 'Grand Total', 'Charged'
    $amount_patterns = [
        '/(?:total(?: paid| due| amount)?|grand total|amount charged|subtotal|jumlah)\s*[:=]?\s*([A-Z]{3}|\$|€|£|¥|₹|Rp\.?)\s*([0-9\.,]+)/i',
        '/([A-Z]{3}|\$|€|£|¥|₹|Rp\.?)\s*([0-9\.,]+)\s*(?:total|paid|charged)/i',
        '/(?:paid|charged)\s*[:=]?\s*([A-Z]{3}|\$|€|£|¥|₹|Rp\.?)\s*([0-9\.,]+)/i'
    ];

    foreach ($amount_patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $raw_curr = strtoupper(trim($m[1]));
            $raw_amt = str_replace([',', ' '], ['', ''], trim($m[2]));
            // Jika desimal menggunakan format Eropa (e.g. 19,99 atau 1.999,00)
            if (substr_count($m[2], ',') === 1 && substr_count($m[2], '.') <= 1 && strpos($m[2], ',') > strpos($m[2], '.')) {
                $raw_amt = str_replace('.', '', $m[2]);
                $raw_amt = str_replace(',', '.', $raw_amt);
            }
            $currency = RECEIPT_CURRENCY_SYMBOLS[$raw_curr] ?? $raw_curr;
            $amount = (float)$raw_amt;
            $is_receipt = true;
            break;
        }
    }

    // Fallback amount jika pattern ketat di atas gagal tapi ada currency + angka
    if ($amount === null) {
        if (preg_match('/(\$|USD|EUR|€|GBP|£|IDR|Rp\.?)\s*([0-9]+(?:\.[0-9]{2})?)/i', $text, $m)) {
            $raw_curr = strtoupper(trim($m[1]));
            $currency = RECEIPT_CURRENCY_SYMBOLS[$raw_curr] ?? $raw_curr;
            $amount = (float)$m[2];
        }
    }

    $summary = $is_receipt
        ? sprintf("Receipt: %s %s from %s (Ref: %s)", $currency, number_format((float)$amount, 2), $vendor ?? 'Unknown', $invoice_no ?? 'N/A')
        : "No clear receipt details detected";

    return [
        'is_receipt' => $is_receipt,
        'vendor' => $vendor,
        'amount' => $amount,
        'currency' => $currency,
        'invoice_number' => $invoice_no,
        'date' => $date,
        'summary' => $summary,
        'text' => $text,
    ];
}
