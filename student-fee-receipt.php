<?php

declare(strict_types=1);

/**
 * AMIRTHAM Community College - Fee Receipt PDF renderer
 *
 * This file only renders/displays the FPDF receipt.
 * All receipt database/data logic lives in api/student-fee-receipt.php.
 *
 * Open this file from the receipt buttons using a normal POST navigation.
 * The Bearer token is posted to this renderer and is never placed in the URL.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/include/bootstrap.php';

// Load the receipt data service without running the JSON endpoint.
define('STUDENT_FEE_RECEIPT_LIBRARY_MODE', true);
require_once __DIR__ . '/api/student-fee-receipt.php';

function receipt_pdf_fail(string $message, int $status = 400): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    echo $message;
    exit;
}

function receipt_pdf_address(array $settings): string
{
    $parts = [];

    foreach (['address_line_1', 'address_line_2'] as $key) {
        $value = cfr_clean_text($settings[$key] ?? '');
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    $cityParts = [];
    foreach (['city', 'state', 'pincode'] as $key) {
        $value = cfr_clean_text($settings[$key] ?? '');
        if ($value !== '') {
            $cityParts[] = $value;
        }
    }
    if ($cityParts !== []) {
        $parts[] = implode(', ', $cityParts);
    }

    $country = cfr_clean_text($settings['country'] ?? '');
    if ($country !== '' && (count($parts) > 0 || strcasecmp($country, 'India') !== 0)) {
        $parts[] = $country;
    }

    return implode(' | ', $parts);
}

function receipt_pdf_asset(string $storedPath): ?string
{
    $storedPath = trim($storedPath);
    if ($storedPath === '' || str_starts_with($storedPath, 'data:') || str_starts_with($storedPath, 'blob:')) {
        return null;
    }

    $pathOnly = $storedPath;
    if (preg_match('~^https?://~i', $storedPath)) {
        $urlPath = parse_url($storedPath, PHP_URL_PATH);
        if (!is_string($urlPath) || $urlPath === '') {
            return null;
        }
        $pathOnly = $urlPath;
    }

    $pathOnly = strtok($pathOnly, '?') ?: $pathOnly;
    $pathOnly = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pathOnly);
    $relative = ltrim($pathOnly, '/\\');

    $candidates = [];

    if (preg_match('~^[A-Za-z]:[\\\\/]~', $storedPath) || str_starts_with($pathOnly, DIRECTORY_SEPARATOR)) {
        $candidates[] = $pathOnly;
    }

    // Application root - this PDF renderer is located in the project root.
    $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . $relative;

    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    if ($docRoot !== '') {
        $candidates[] = $docRoot . DIRECTORY_SEPARATOR . $relative;
    }

    // Helpful when the project itself is installed in a subdirectory.
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = trim(dirname($scriptName), '/.');
    if ($docRoot !== '' && $scriptDir !== '') {
        $candidates[] = $docRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $scriptDir) . DIRECTORY_SEPARATOR . $relative;
    }

    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$GLOBALS['receipt_pdf_temp_images'] = [];

function receipt_pdf_image(?string $path): ?string
{
    if (!$path || !is_file($path)) {
        return null;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        return $path;
    }

    if ($ext === 'webp' && function_exists('imagecreatefromwebp') && function_exists('imagepng')) {
        $image = @imagecreatefromwebp($path);
        if ($image !== false) {
            $tmp = tempnam(sys_get_temp_dir(), 'amr_receipt_');
            if ($tmp !== false) {
                $png = $tmp . '.png';
                @unlink($tmp);
                if (@imagepng($image, $png)) {
                    imagedestroy($image);
                    $GLOBALS['receipt_pdf_temp_images'][] = $png;
                    return $png;
                }
                imagedestroy($image);
            }
        }
    }

    return null;
}

register_shutdown_function(static function (): void {
    foreach (($GLOBALS['receipt_pdf_temp_images'] ?? []) as $file) {
        if (is_string($file) && is_file($file)) {
            @unlink($file);
        }
    }
});

class CommunityCollegeFeeReceiptPDF extends FPDF
{
    private float $left = 14.0;
    private float $width = 182.0;
    private string $currency = 'Rs.';
    private string $dateFormat = 'd-m-Y';
    private ?float $receiptOuterTop = null;

    public function configure(array $settings): void
    {
        $symbol = trim((string)($settings['currency_symbol'] ?? 'Rs.'));
        $this->currency = ($symbol === '' || $symbol === '₹') ? 'Rs.' : $symbol;
        $this->dateFormat = trim((string)($settings['date_format'] ?? 'd-m-Y')) ?: 'd-m-Y';
    }

    public function tx(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }
        return $text;
    }

    public function money(float $amount): string
    {
        return $this->currency . ' ' . number_format($amount, 2);
    }

    public function dateText(?string $date): string
    {
        $date = trim((string)$date);
        if ($date === '') {
            return '-';
        }

        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $value ? $value->format($this->dateFormat) : $date;
    }

    private function wrappedLines(string $text, float $width): array
    {
        $text = $this->tx(trim($text));
        if ($text === '') {
            return [''];
        }

        $paragraphs = preg_split('/\n/', $text) ?: [$text];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }

            $words = preg_split('/\s+/', $paragraph) ?: [$paragraph];
            $line = '';

            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($this->GetStringWidth($candidate) <= $width) {
                    $line = $candidate;
                    continue;
                }

                if ($line !== '') {
                    $lines[] = $line;
                }
                $line = $word;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines ?: [''];
    }

    private function textHeight(string $text, float $width, float $lineHeight): float
    {
        return max(1, count($this->wrappedLines($text, $width))) * $lineHeight;
    }

    private function drawTextLines(float $x, float $y, float $width, string $text, float $lineHeight, string $align = 'L'): float
    {
        $lines = $this->wrappedLines($text, $width);
        foreach ($lines as $index => $line) {
            $this->SetXY($x, $y + ($index * $lineHeight));
            $this->Cell($width, $lineHeight, $line, 0, 0, $align);
        }
        return count($lines) * $lineHeight;
    }

    private function ensure(float $height): void
    {
        if ($this->GetY() + $height > 282.0) {
            $this->AddPage();
        }
    }

    public function receiptHeader(array $settings): void
    {
        $x = $this->left;
        $y = 13.0;
        $w = $this->width;

        // Start one continuous outer border for the complete receipt.
        $this->receiptOuterTop = 10.5;

        $logo = receipt_pdf_image(receipt_pdf_asset((string)($settings['company_logo'] ?? '')));
        $logoW = $logo ? 22.0 : 0.0;

        if ($logo) {
            try {
                $this->Image($logo, $x, $y + 1.5, $logoW, 0);
            } catch (Throwable $e) {
                $logo = null;
                $logoW = 0.0;
            }
        }

        $textX = $logo ? $x + 27.0 : $x;
        $textW = $logo ? $w - 27.0 : $w;

        $businessName = cfr_clean_text($settings['business_name'] ?? 'AMIRTHAM COMMUNITY COLLEGE');
        $address = receipt_pdf_address($settings);

        $contact = [];
        $mobile = cfr_clean_text($settings['business_mobile'] ?? '');
        $email = cfr_clean_text($settings['business_email'] ?? '');
        $website = cfr_clean_text($settings['website'] ?? '');
        if ($mobile !== '') $contact[] = 'Phone: ' . $mobile;
        if ($email !== '') $contact[] = 'Email: ' . $email;
        if ($website !== '') $contact[] = $website;
        $contactText = implode(' | ', $contact);

        $tax = [];
        $gst = cfr_clean_text($settings['gst_number'] ?? '');
        $pan = cfr_clean_text($settings['pan_number'] ?? '');
        if ($gst !== '') $tax[] = 'GSTIN: ' . $gst;
        if ($pan !== '') $tax[] = 'PAN: ' . $pan;
        $taxText = implode(' | ', $tax);

        $cursor = $y;
        $this->SetFont('Arial', 'B', 16);
        $cursor += $this->drawTextLines($textX, $cursor, $textW, $businessName, 6.8, 'C');

        $this->SetFont('Arial', 'B', 10.5);
        $this->SetXY($textX, $cursor + 0.5);
        $this->Cell($textW, 5.5, 'FEE RECEIPT', 0, 0, 'C');
        $cursor += 6.0;

        $this->SetFont('Arial', '', 8.2);
        if ($address !== '') {
            $cursor += $this->drawTextLines($textX, $cursor, $textW, $address, 4.2, 'C');
        }
        if ($contactText !== '') {
            $cursor += $this->drawTextLines($textX, $cursor, $textW, $contactText, 4.2, 'C');
        }
        if ($taxText !== '') {
            $this->SetFont('Arial', 'B', 8.0);
            $cursor += $this->drawTextLines($textX, $cursor, $textW, $taxText, 4.2, 'C');
        }

        $headerBottom = max($cursor + 2.5, $logo ? $y + 24.0 : $cursor + 2.5);
        $this->SetDrawColor(75, 75, 75);
        $this->SetLineWidth(0.35);
        $this->Line($x, $headerBottom, $x + $w, $headerBottom);
        // Next section starts exactly on this separator line.
        $this->SetY($headerBottom);
    }

    public function receiptMeta(array $receipt): void
    {
        $x = $this->left;
        $w = $this->width;
        $col = $w / 3.0;
        $y = $this->GetY();

        $items = [
            ['Receipt No', (string)$receipt['receipt_no']],
            ['Admission No', (string)$receipt['admission_no']],
            ['Date', $this->dateText((string)$receipt['receipt_date'])],
        ];

        // Keep this as one clean row inside the main receipt border.
        // No internal vertical borders between Receipt No, Admission No and Date.
        foreach ($items as $i => [$label, $value]) {
            $cellX = $x + ($i * $col);

            $this->SetFont('Arial', '', 7.8);
            $this->SetXY($cellX + 3.0, $y + 2.0);
            $this->Cell($col - 6.0, 4.0, $this->tx($label), 0, 0, 'L');

            $this->SetFont('Arial', 'B', 9.2);
            $this->SetXY($cellX + 3.0, $y + 6.4);
            $this->Cell($col - 6.0, 4.5, $this->tx($value), 0, 0, 'L');
        }

        // One horizontal separator only at the bottom of the meta row.
        $this->SetDrawColor(90, 90, 90);
        $this->Line($x, $y + 13.0, $x + $w, $y + 13.0);

        // Join directly with the next section; no white gap.
        $this->SetY($y + 13.0);
    }

    private function kvBodyHeight(array $rows, float $colW): float
    {
        $labelW = 36.0;
        $valueW = $colW - $labelW - 8.0;
        $height = 4.0;

        foreach ($rows as [$label, $value]) {
            $this->SetFont('Arial', '', 8.4);
            $lh = $this->textHeight((string)$label, $labelW, 4.8);
            $this->SetFont('Arial', 'B', 8.4);
            $vh = $this->textHeight((string)$value, $valueW, 4.8);
            $height += max(5.6, $lh, $vh);
        }

        return $height + 2.0;
    }

    private function drawKvRows(float $x, float $y, float $colW, array $rows): void
    {
        $labelW = 36.0;
        $valueW = $colW - $labelW - 8.0;
        $cursor = $y + 2.0;

        foreach ($rows as [$label, $value]) {
            $this->SetFont('Arial', '', 8.4);
            $labelLines = $this->wrappedLines((string)$label, $labelW);
            $this->SetFont('Arial', 'B', 8.4);
            $valueLines = $this->wrappedLines((string)$value, $valueW);
            $rowH = max(5.6, count($labelLines) * 4.8, count($valueLines) * 4.8);

            $this->SetFont('Arial', '', 8.4);
            $this->drawTextLines($x + 4.0, $cursor, $labelW, (string)$label, 4.8, 'L');

            $this->SetFont('Arial', 'B', 8.4);
            $this->drawTextLines($x + 4.0 + $labelW, $cursor, $valueW, (string)$value, 4.8, 'L');

            $cursor += $rowH;
        }
    }

    public function twoColumnDetails(string $leftTitle, array $leftRows, string $rightTitle, array $rightRows): void
    {
        $x = $this->left;
        $w = $this->width;
        $half = $w / 2.0;
        $headerH = 8.0;

        $leftBodyH = $this->kvBodyHeight($leftRows, $half);
        $rightBodyH = $this->kvBodyHeight($rightRows, $half);
        $bodyH = max($leftBodyH, $rightBodyH, 28.0);
        $totalH = $headerH + $bodyH;

        $this->ensure($totalH + 5.0);
        $y = $this->GetY();

        $this->SetDrawColor(90, 90, 90);
        $this->SetFillColor(245, 245, 245);
        $this->Rect($x, $y, $w, $totalH);
        $this->Line($x + $half, $y, $x + $half, $y + $totalH);
        $this->Line($x, $y + $headerH, $x + $w, $y + $headerH);

        $this->Rect($x, $y, $half, $headerH, 'DF');
        $this->Rect($x + $half, $y, $half, $headerH, 'DF');

        $this->SetFont('Arial', 'B', 9.2);
        $this->SetXY($x + 2.0, $y);
        $this->Cell($half - 4.0, $headerH, $this->tx($leftTitle), 0, 0, 'L');
        $this->SetXY($x + $half + 2.0, $y);
        $this->Cell($half - 4.0, $headerH, $this->tx($rightTitle), 0, 0, 'L');

        $this->drawKvRows($x, $y + $headerH, $half, $leftRows);
        $this->drawKvRows($x + $half, $y + $headerH, $half, $rightRows);

        // Join directly with the next section.
        $this->SetY($y + $totalH);
    }

    private function sectionHeader(string $title): void
    {
        $this->SetDrawColor(90, 90, 90);
        $this->SetFillColor(245, 245, 245);
        $this->SetFont('Arial', 'B', 9.2);
        $this->Cell($this->width, 8.0, $this->tx($title), 1, 1, 'L', true);
    }

    public function studentDetailsBlock(array $rows): void
    {
        $x = $this->left;
        $w = $this->width;
        $headerH = 8.0;
        $labelW = 38.0;
        $valueW = $w - $labelW - 8.0;

        $bodyH = 4.0;
        foreach ($rows as [$label, $value]) {
            $this->SetFont('Arial', '', 8.4);
            $labelH = $this->textHeight((string)$label, $labelW, 4.8);
            $this->SetFont('Arial', 'B', 8.4);
            $valueH = $this->textHeight((string)$value, $valueW, 4.8);
            $bodyH += max(5.6, $labelH, $valueH);
        }
        $bodyH += 2.0;
        $totalH = $headerH + $bodyH;

        $this->ensure($totalH + 2.0);
        $y = $this->GetY();

        $this->SetDrawColor(90, 90, 90);
        $this->SetFillColor(245, 245, 245);
        $this->SetLineWidth(0.25);

        // Keep the section joined to the receipt with only the outer edges
        // and the title separator. No row-by-row borders in the body.
        $this->Line($x, $y, $x + $w, $y);
        $this->Line($x, $y, $x, $y + $totalH);
        $this->Line($x + $w, $y, $x + $w, $y + $totalH);
        $this->Line($x, $y + $headerH, $x + $w, $y + $headerH);
        $this->Line($x, $y + $totalH, $x + $w, $y + $totalH);

        $this->Rect($x, $y, $w, $headerH, 'F');
        $this->SetFont('Arial', 'B', 9.2);
        $this->SetXY($x + 2.0, $y);
        $this->Cell($w - 4.0, $headerH, $this->tx('STUDENT DETAILS'), 0, 0, 'L');

        $cursor = $y + $headerH + 2.0;
        foreach ($rows as [$label, $value]) {
            $this->SetFont('Arial', '', 8.4);
            $labelLines = $this->wrappedLines((string)$label, $labelW);
            $this->SetFont('Arial', 'B', 8.4);
            $valueLines = $this->wrappedLines((string)$value, $valueW);
            $rowH = max(5.6, count($labelLines) * 4.8, count($valueLines) * 4.8);

            $this->SetFont('Arial', '', 8.4);
            $this->drawTextLines($x + 4.0, $cursor, $labelW, (string)$label, 4.8, 'L');

            $this->SetFont('Arial', 'B', 8.4);
            $this->drawTextLines($x + 4.0 + $labelW, $cursor, $valueW, (string)$value, 4.8, 'L');

            $cursor += $rowH;
        }

        $this->SetY($y + $totalH);
    }

    public function admissionFeeDetailsTable(
        array $items,
        float $totalFee,
        float $admissionPayment,
        float $balanceAmount
    ): void {
        $this->ensure(38.0 + (count($items) * 7.0));
        $this->sectionHeader('FEE DETAILS');

        $x = $this->left;
        $widths = [18.0, 124.0, 40.0];
        $headers = ['S.No', 'Fee Particular', 'Amount'];

        $this->SetFont('Arial', 'B', 8.2);
        foreach ($headers as $i => $header) {
            $this->Cell(
                $widths[$i],
                7.0,
                $this->tx($header),
                1,
                $i === 2 ? 1 : 0,
                $i === 0 ? 'C' : ($i === 2 ? 'R' : 'L')
            );
        }

        $bodyTop = $this->GetY();

        foreach ($items as $index => $item) {
            $this->SetFont('Arial', '', 8.2);
            $particular = cfr_clean_text($item['fee_head'] ?? '');
            if ($particular === '') {
                $particular = 'Fee';
            }

            $particularLines = $this->wrappedLines($particular, $widths[1] - 3.0);
            $rowH = max(7.0, (count($particularLines) * 4.5) + 2.0);
            $this->ensure($rowH + 22.0);

            $y = $this->GetY();

            $this->SetXY($x, $y);
            $this->Cell($widths[0], $rowH, (string)($index + 1), 0, 0, 'C');

            $this->drawTextLines(
                $x + $widths[0] + 1.5,
                $y + 1.0,
                $widths[1] - 3.0,
                $particular,
                4.5,
                'L'
            );

            $this->SetXY($x + $widths[0] + $widths[1], $y);
            $this->Cell(
                $widths[2] - 2.0,
                $rowH,
                $this->tx($this->money((float)($item['amount'] ?? 0))),
                0,
                0,
                'R'
            );

            $this->SetXY($x, $y + $rowH);
        }

        $bodyBottom = $this->GetY();

        // Clean tbody: no horizontal lines between fee items. Keep the
        // column guides and one separator before the totals.
        if ($bodyBottom > $bodyTop) {
            $this->SetDrawColor(90, 90, 90);
            $this->Line($x + $widths[0], $bodyTop, $x + $widths[0], $bodyBottom);
            $this->Line(
                $x + $widths[0] + $widths[1],
                $bodyTop,
                $x + $widths[0] + $widths[1],
                $bodyBottom
            );
        }

        $this->SetDrawColor(90, 90, 90);
        $this->Line($x, $bodyBottom, $x + $this->width, $bodyBottom);

        $labelW = $widths[0] + $widths[1];
        $amountW = $widths[2];
        $totals = [
            ['Total Fee', $totalFee],
            ['Admission Payment', $admissionPayment],
            ['Balance Amount', $balanceAmount],
        ];

        foreach ($totals as [$label, $amount]) {
            $this->SetFont('Arial', 'B', 8.3);
            $this->Cell($labelW, 7.0, $this->tx((string)$label), 0, 0, 'R');
            $this->Cell($amountW, 7.0, $this->tx($this->money((float)$amount)), 0, 1, 'R');
        }

        $this->Line($x, $this->GetY(), $x + $this->width, $this->GetY());
    }

    public function allocationTable(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->ensure(24.0);
        $this->sectionHeader('ALLOCATION DETAILS');

        $widths = [18.0, 44.0, 54.0, 66.0];
        $headers = ['S.No', 'EMI', 'Due Date', 'Applied Amount'];
        $this->SetFont('Arial', 'B', 8.2);

        foreach ($headers as $i => $header) {
            $this->Cell($widths[$i], 7.0, $this->tx($header), 1, $i === 3 ? 1 : 0, $i === 3 ? 'R' : ($i === 0 ? 'C' : 'L'));
        }

        $total = 0.0;
        foreach ($rows as $index => $row) {
            $this->ensure(8.0);
            $amount = (float)$row['allocated_amount'];
            $total += $amount;

            $this->SetFont('Arial', '', 8.2);
            $this->Cell($widths[0], 7.0, (string)($index + 1), 1, 0, 'C');
            $this->Cell($widths[1], 7.0, $this->tx('EMI ' . $row['installment_number']), 1, 0, 'L');
            $this->Cell($widths[2], 7.0, $this->tx($this->dateText((string)$row['due_date'])), 1, 0, 'C');
            $this->Cell($widths[3], 7.0, $this->tx($this->money($amount)), 1, 1, 'R');
        }

        $this->SetFont('Arial', 'B', 8.2);
        $this->Cell($widths[0] + $widths[1] + $widths[2], 7.0, 'Total', 1, 0, 'R');
        $this->Cell($widths[3], 7.0, $this->tx($this->money($total)), 1, 1, 'R');
    }

    public function paymentTable(array $rows): float
    {
        $this->ensure(28.0);
        $this->sectionHeader('PAYMENT MODE DETAILS');

        // Account column removed. Keep only Mode, Reference No and Amount.
        $widths = [45.0, 97.0, 40.0];
        $headers = ['Mode', 'Reference No', 'Amount'];

        $this->SetFont('Arial', 'B', 8.2);
        foreach ($headers as $i => $header) {
            $this->Cell(
                $widths[$i],
                7.0,
                $this->tx($header),
                1,
                $i === 2 ? 1 : 0,
                $i === 2 ? 'R' : 'L'
            );
        }

        $total = 0.0;
        $bodyTop = $this->GetY();
        $bodyStartX = $this->left;

        foreach ($rows as $row) {
            $this->SetFont('Arial', '', 8.1);
            $modeLines = $this->wrappedLines((string)$row['mode'], $widths[0] - 3.0);
            $refLines = $this->wrappedLines((string)$row['reference'], $widths[1] - 3.0);
            $lineCount = max(count($modeLines), count($refLines), 1);
            $rowH = max(7.0, ($lineCount * 4.5) + 2.0);
            $this->ensure($rowH + 8.0);

            $x = $this->left;
            $y = $this->GetY();

            // No horizontal borders between tbody rows.
            $this->SetFont('Arial', '', 8.1);
            $this->drawTextLines(
                $x + 1.5,
                $y + 1.0,
                $widths[0] - 3.0,
                (string)$row['mode'],
                4.5,
                'L'
            );
            $this->drawTextLines(
                $x + $widths[0] + 1.5,
                $y + 1.0,
                $widths[1] - 3.0,
                (string)$row['reference'],
                4.5,
                'L'
            );

            $this->SetXY($x + $widths[0] + $widths[1], $y);
            $this->Cell(
                $widths[2] - 2.0,
                $rowH,
                $this->tx($this->money((float)$row['amount'])),
                0,
                0,
                'R'
            );
            $this->SetXY($x, $y + $rowH);

            $total += (float)$row['amount'];
        }

        $bodyBottom = $this->GetY();

        // Keep only the vertical column separators through tbody.
        if ($bodyBottom > $bodyTop) {
            $this->SetDrawColor(90, 90, 90);
            $this->Line($bodyStartX + $widths[0], $bodyTop, $bodyStartX + $widths[0], $bodyBottom);
            $this->Line(
                $bodyStartX + $widths[0] + $widths[1],
                $bodyTop,
                $bodyStartX + $widths[0] + $widths[1],
                $bodyBottom
            );
        }

        // Single separator before Total Paid; no row-by-row horizontal lines.
        $this->SetDrawColor(90, 90, 90);
        $this->Line($this->left, $bodyBottom, $this->left + $this->width, $bodyBottom);

        $this->SetFont('Arial', 'B', 8.4);
        $this->Cell(142.0, 7.0, 'Total Paid', 0, 0, 'R');
        $this->Cell(40.0, 7.0, $this->tx($this->money($total)), 0, 1, 'R');

        // Close the payment section with one bottom separator.
        $this->Line($this->left, $this->GetY(), $this->left + $this->width, $this->GetY());

        return round($total, 2);
    }

    public function labeledBox(string $label, string $value, float $minimumHeight = 10.0): void
    {
        $labelW = 38.0;
        $valueW = $this->width - $labelW;

        $this->SetFont('Arial', '', 8.4);
        $valueH = $this->textHeight($value, $valueW - 5.0, 4.6);
        $h = max($minimumHeight, $valueH + 3.0);
        $this->ensure($h + 2.0);

        $x = $this->GetX();
        $y = $this->GetY();
        $this->Rect($x, $y, $labelW, $h);
        $this->Rect($x + $labelW, $y, $valueW, $h);

        $this->SetFont('Arial', 'B', 8.4);
        $this->SetXY($x + 3.0, $y + 1.8);
        $this->Cell($labelW - 6.0, 5.0, $this->tx($label), 0, 0, 'L');

        $this->SetFont('Arial', '', 8.4);
        $this->drawTextLines($x + $labelW + 3.0, $y + 1.8, $valueW - 6.0, $value, 4.6, 'L');

        $this->SetY($y + $h);
    }

    public function signatureBlock(array $settings, string $receivedBy): void
    {
        $this->ensure(36.0);
        $this->Ln(7.0);

        $x = $this->left;
        $y = $this->GetY();
        $half = $this->width / 2.0;

        $signature = receipt_pdf_image(receipt_pdf_asset((string)($settings['digital_signature'] ?? '')));
        if ($signature) {
            try {
                $this->Image($signature, $x + $half + 49.0, $y, 28.0, 11.0);
            } catch (Throwable $e) {
                // Optional image.
            }
        }

        $this->SetY($y + 12.0);
        $this->SetFont('Arial', '', 8.2);
        $this->Cell($half, 5.0, 'Received By', 0, 0, 'L');
        $this->Cell($half, 5.0, 'Authorized Signature', 0, 1, 'R');

        $this->SetFont('Arial', 'B', 8.2);
        $this->Cell($half, 5.0, $this->tx($receivedBy !== '' ? $receivedBy : '-'), 0, 0, 'L');

        $signatory = cfr_clean_text($settings['authorized_signatory'] ?? '');
        $this->Cell($half, 5.0, $this->tx($signatory !== '' ? $signatory : '____________________'), 0, 1, 'R');
    }

    public function receiptFooter(array $settings): void
    {
        $footer = cfr_clean_text($settings['invoice_footer'] ?? '');
        if ($footer !== '') {
            $this->Ln(3.0);
            $this->SetFont('Arial', '', 7.8);
            $height = $this->textHeight($footer, $this->width, 4.2);
            $this->ensure($height + 5.0);
            $this->drawTextLines($this->left, $this->GetY(), $this->width, $footer, 4.2, 'C');
            $this->SetY($this->GetY() + $height);
        }

        $this->Ln(2.5);
        $this->SetFont('Arial', 'I', 7.4);
        $this->Cell($this->width, 5.0, 'This is a computer-generated fee receipt.', 0, 1, 'C');
    }


    public function closingSection(
        string $amountInWords,
        string $remarks,
        array $settings,
        string $receivedBy
    ): void {
        $x = $this->left;
        $w = $this->width;
        $labelW = 38.0;
        $valueW = $w - $labelW;

        $this->SetFont('Arial', '', 8.4);
        $amountValueH = $this->textHeight($amountInWords, $valueW - 6.0, 4.6);
        $amountH = max(10.0, $amountValueH + 3.6);

        $remarksText = cfr_clean_text($remarks);
        if ($remarksText === '') {
            $remarksText = '-';
        }
        $remarksValueH = $this->textHeight($remarksText, $valueW - 6.0, 4.6);
        $remarksH = max(11.0, $remarksValueH + 3.6);

        $signatureH = 29.0;

        $footer = cfr_clean_text($settings['invoice_footer'] ?? '');
        $footerTextH = 0.0;
        if ($footer !== '') {
            $this->SetFont('Arial', '', 7.8);
            $footerTextH = $this->textHeight($footer, $w - 8.0, 4.2);
        }
        $footerH = max(10.0, $footerTextH + ($footer !== '' ? 7.5 : 0.0) + 5.0);

        $totalH = $amountH + $remarksH + $signatureH + $footerH;

        // Keep the complete closing area together so the receipt border never
        // breaks across pages while printing.
        $this->ensure($totalH + 2.0);
        $y = $this->GetY();

        $this->SetDrawColor(90, 90, 90);
        $this->SetLineWidth(0.25);

        // One continuous outer border for Amount in Words, Remarks,
        // Signature and Footer.
        $this->Rect($x, $y, $w, $totalH);

        $amountBottom = $y + $amountH;
        $remarksBottom = $amountBottom + $remarksH;
        $signatureBottom = $remarksBottom + $signatureH;

        $this->Line($x, $amountBottom, $x + $w, $amountBottom);
        $this->Line($x, $remarksBottom, $x + $w, $remarksBottom);
        $this->Line($x, $signatureBottom, $x + $w, $signatureBottom);

        // Label/value divider only for Amount in Words and Remarks.
        $this->Line($x + $labelW, $y, $x + $labelW, $remarksBottom);

        $this->SetFont('Arial', 'B', 8.4);
        $this->SetXY($x + 3.0, $y + 1.8);
        $this->Cell($labelW - 6.0, 5.0, $this->tx('Amount in Words :'), 0, 0, 'L');

        $this->SetFont('Arial', '', 8.4);
        $this->drawTextLines(
            $x + $labelW + 3.0,
            $y + 1.8,
            $valueW - 6.0,
            $amountInWords,
            4.6,
            'L'
        );

        $this->SetFont('Arial', 'B', 8.4);
        $this->SetXY($x + 3.0, $amountBottom + 1.8);
        $this->Cell($labelW - 6.0, 5.0, $this->tx('Remarks :'), 0, 0, 'L');

        $this->SetFont('Arial', '', 8.4);
        $this->drawTextLines(
            $x + $labelW + 3.0,
            $amountBottom + 1.8,
            $valueW - 6.0,
            $remarksText,
            4.6,
            'L'
        );

        // Signature area stays fully inside the same border.
        $half = $w / 2.0;
        $signatureTop = $remarksBottom;
        $signature = receipt_pdf_image(receipt_pdf_asset((string)($settings['digital_signature'] ?? '')));

        if ($signature) {
            try {
                $this->Image($signature, $x + $half + 49.0, $signatureTop + 2.0, 28.0, 11.0);
            } catch (Throwable $e) {
                // Digital signature is optional.
            }
        }

        $this->SetFont('Arial', '', 8.2);
        $this->SetXY($x + 4.0, $signatureTop + 14.0);
        $this->Cell($half - 8.0, 5.0, 'Received By', 0, 0, 'L');

        $this->SetXY($x + $half + 4.0, $signatureTop + 14.0);
        $this->Cell($half - 8.0, 5.0, 'Authorized Signature', 0, 0, 'R');

        $this->SetFont('Arial', 'B', 8.2);
        $this->SetXY($x + 4.0, $signatureTop + 19.0);
        $this->Cell(
            $half - 8.0,
            5.0,
            $this->tx($receivedBy !== '' ? $receivedBy : '-'),
            0,
            0,
            'L'
        );

        $signatory = cfr_clean_text($settings['authorized_signatory'] ?? '');
        $this->SetXY($x + $half + 4.0, $signatureTop + 19.0);
        $this->Cell(
            $half - 8.0,
            5.0,
            $this->tx($signatory !== '' ? $signatory : '____________________'),
            0,
            0,
            'R'
        );

        // Footer is also enclosed by the same outer border.
        $footerTop = $signatureBottom;
        $cursorY = $footerTop + 2.0;

        if ($footer !== '') {
            $this->SetFont('Arial', '', 7.8);
            $drawn = $this->drawTextLines(
                $x + 4.0,
                $cursorY,
                $w - 8.0,
                $footer,
                4.2,
                'C'
            );
            $cursorY += $drawn + 1.0;
        }

        $this->SetFont('Arial', 'I', 7.4);
        $this->SetXY($x + 4.0, $cursorY);
        $this->Cell(
            $w - 8.0,
            5.0,
            'This is a computer-generated fee receipt.',
            0,
            0,
            'C'
        );

        $this->SetY($y + $totalH);
    }

    public function finishOuterBorder(): void
    {
        if ($this->receiptOuterTop === null) {
            return;
        }

        $bottom = $this->GetY();
        $height = max(0.0, $bottom - $this->receiptOuterTop);

        $this->SetDrawColor(65, 65, 65);
        $this->SetLineWidth(0.40);
        $this->Rect($this->left, $this->receiptOuterTop, $this->width, $height);

        // Restore the regular internal-line width for consistency.
        $this->SetLineWidth(0.25);
    }
}

// Normal browser navigation cannot attach the Authorization header used by
// this API-only application. Receipt buttons therefore POST the existing
// Bearer token to this renderer. Convert it to the same server variable used
// by require_user(), without exposing the token in the browser URL.
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $postedToken = trim((string)($_POST['auth_token'] ?? ''));
    if ($postedToken !== '' && empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $postedToken = preg_replace('/[\r\n]+/', '', $postedToken) ?? '';
        if ($postedToken !== '') {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $postedToken;
        }
    }
    unset($_POST['auth_token']);
}

$receiptRef = (string)($_POST['ref'] ?? $_GET['ref'] ?? '');

try {
    $user = require_user();
    $data = cfr_payload_from_ref($receiptRef, $user);
} catch (InvalidArgumentException $e) {
    receipt_pdf_fail($e->getMessage(), 422);
} catch (OutOfBoundsException $e) {
    receipt_pdf_fail($e->getMessage(), 404);
} catch (DomainException $e) {
    receipt_pdf_fail($e->getMessage(), 403);
} catch (Throwable $e) {
    receipt_pdf_fail($e->getMessage(), 500);
}

$receipt = $data['receipt'];
$settings = $data['settings'];
$payments = $data['payments'];
$feeItems = $data['fee_items'] ?? [];
$allocations = $data['allocations'];
$emi = $data['emi'];
$kind = $data['kind'];
$summary = $data['summary'];

$pdf = new CommunityCollegeFeeReceiptPDF('P', 'mm', 'A4');
$pdf->SetMargins(14, 12, 14);
$pdf->SetAutoPageBreak(true, 15);
$pdf->configure($settings);
$pdf->AddPage();
$pdf->receiptHeader($settings);
$pdf->receiptMeta($receipt);

$studentRows = [
    ['Student Name', (string)$receipt['student_name']],
    ['Student Code', (string)$receipt['student_code']],
    ['Mobile', cfr_clean_text($receipt['mobile'] ?? '') !== '' ? (string)$receipt['mobile'] : '-'],
    ['Course', trim((string)$receipt['course_code'] . ' - ' . (string)$receipt['course_name'], ' -')],
    ['Batch', trim((string)$receipt['batch_code'] . ' - ' . (string)$receipt['batch_name'], ' -')],
];

if ($kind === 'admission') {
    // Admission receipt: show the exact fee heads saved with this student's
    // fee plan, not only a three-line fee summary.
    $pdf->studentDetailsBlock($studentRows);

    if ($feeItems !== []) {
        $pdf->admissionFeeDetailsTable(
            $feeItems,
            (float)$summary['total_fee'],
            (float)$summary['current_amount'],
            (float)$summary['balance_after']
        );
    } else {
        // Safe fallback for very old data where plan item snapshots are absent.
        $pdf->admissionFeeDetailsTable(
            [[
                'fee_head' => 'Admission Fee',
                'amount' => (float)$summary['total_fee'],
            ]],
            (float)$summary['total_fee'],
            (float)$summary['current_amount'],
            (float)$summary['balance_after']
        );
    }
} else {
    if ($kind === 'emi') {
        $rightTitle = 'EMI DETAILS';
        if ($emi) {
            $rightRows = [
                ['EMI No', 'EMI ' . $emi['installment_number']],
                ['Due Date', $pdf->dateText((string)$emi['due_date'])],
                ['EMI Amount', $pdf->money((float)$emi['due_amount'])],
                ['Previous Paid', $pdf->money((float)$emi['previous_paid'])],
                ['Amount Paid', $pdf->money((float)$emi['paid_now'])],
                ['EMI Balance', $pdf->money((float)$emi['balance_after'])],
                ['Overall Balance', $pdf->money((float)$summary['balance_after'])],
            ];
        } else {
            $rightRows = [
                ['Amount Paid', $pdf->money((float)$summary['current_amount'])],
                ['Overall Balance', $pdf->money((float)$summary['balance_after'])],
            ];
        }
    } else {
        $rightTitle = 'OUTSTANDING DETAILS';
        $rightRows = [
            ['Total Admission Fee', $pdf->money((float)$summary['total_fee'])],
            ['Previous Paid', $pdf->money((float)$summary['previous_paid'])],
            ['Previous Balance', $pdf->money((float)$summary['previous_balance'])],
            ['Amount Paid', $pdf->money((float)$summary['current_amount'])],
            ['Balance Amount', $pdf->money((float)$summary['balance_after'])],
        ];
    }

    $pdf->twoColumnDetails('STUDENT DETAILS', $studentRows, $rightTitle, $rightRows);

    if ($kind === 'overall' && $allocations !== []) {
        $pdf->allocationTable($allocations);
    }
}

$pdf->paymentTable($payments);
$pdf->closingSection(
    (string)$summary['amount_in_words'],
    (string)$summary['remarks'],
    $settings,
    cfr_clean_text($receipt['created_by_username'] ?? '')
);
$pdf->finishOuterBorder();

$fileName = 'Fee_Receipt_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$receipt['receipt_no']) . '.pdf';
$pdf->Output('I', $fileName);
exit;
