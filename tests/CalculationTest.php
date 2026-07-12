<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers the automatic money engine in lib.php: number parsing, formatting,
 * Thai/English amount-in-words, and the per-item + summary totals that the
 * admin editor and printed page both rely on.
 */
final class CalculationTest extends TestCase
{
    // ---- parse_number ---------------------------------------------------

    #[DataProvider('parseNumberProvider')]
    public function testParseNumber(string $input, float $expected): void
    {
        $this->assertSame($expected, parse_number($input));
    }

    public static function parseNumberProvider(): array
    {
        return [
            'plain' => ['15000', 15000.0],
            'thousands separator' => ['15,000', 15000.0],
            'percent sign' => ['10%', 10.0],
            'decimal' => ['1200.50', 1200.5],
            'currency suffix' => ['41,600.00 บาท', 41600.0],
            'empty' => ['', 0.0],
            'non-numeric' => ['abc', 0.0],
        ];
    }

    // ---- formatting -----------------------------------------------------

    public function testFormatAmountUsesTwoDecimalsAndGrouping(): void
    {
        $this->assertSame('9,600.00', format_amount(9600));
        $this->assertSame('1,234,567.50', format_amount(1234567.5));
        $this->assertSame('0.00', format_amount(0));
    }

    public function testFormatMoneyAppendsCurrencyOnlyWhenPresent(): void
    {
        $this->assertSame('9,600.00 บาท', format_money(9600, 'บาท'));
        $this->assertSame('9,600.00', format_money(9600, ''));
    }

    // ---- Thai baht text -------------------------------------------------

    #[DataProvider('thaiWordsProvider')]
    public function testThaiBahtText(float $amount, string $expected): void
    {
        $this->assertSame($expected, thai_baht_text($amount));
    }

    public static function thaiWordsProvider(): array
    {
        return [
            'zero' => [0, 'ศูนย์บาทถ้วน'],
            'twenty one uses เอ็ด' => [21, 'ยี่สิบเอ็ดบาทถ้วน'],
            'one hundred eleven' => [111, 'หนึ่งร้อยสิบเอ็ดบาทถ้วน'],
            'sample invoice total' => [43500, 'สี่หมื่นสามพันห้าร้อยบาทถ้วน'],
            'exactly one million' => [1000000, 'หนึ่งล้านบาทถ้วน'],
            'with satang' => [1234567.5, 'หนึ่งล้านสองแสนสามหมื่นสี่พันห้าร้อยหกสิบเจ็ดบาทห้าสิบสตางค์'],
        ];
    }

    // ---- English baht text ----------------------------------------------

    public function testEnglishBahtText(): void
    {
        $this->assertSame('Forty-Four Thousand Five Hundred Twelve Baht Only', english_baht_text(44512));
        $this->assertSame('Fifteen Thousand Six Hundred Twenty-Two Baht Only', english_baht_text(15622));
        $this->assertSame(
            'One Million Two Hundred Thirty-Four Thousand Five Hundred Sixty-Seven Baht and Fifty Satang',
            english_baht_text(1234567.5)
        );
    }

    public function testAmountInWordsDispatchesByLanguage(): void
    {
        $this->assertSame('สี่หมื่นสามพันห้าร้อยบาทถ้วน', amount_in_words(43500, 'th'));
        $this->assertSame('Forty-Three Thousand Five Hundred Baht Only', amount_in_words(43500, 'en'));
        // Unknown language falls back to Thai.
        $this->assertSame('สี่หมื่นสามพันห้าร้อยบาทถ้วน', amount_in_words(43500, 'xx'));
    }

    // ---- compute_invoice_totals -----------------------------------------

    public function testComputeDerivesLineTotalsWithPercentDiscount(): void
    {
        $result = compute_invoice_totals([
            'currency' => 'บาท',
            'items' => [
                ['qty' => '2', 'price' => '6000', 'discount' => '20', 'vat' => '7'],
                ['qty' => '1', 'price' => '5000', 'discount' => '0', 'vat' => '7'],
            ],
        ]);

        // 2 * 6000 * 0.8 = 9,600 ; 1 * 5000 = 5,000
        $this->assertSame('9,600.00', $result['items'][0]['pre_tax']);
        $this->assertSame('5,000.00', $result['items'][1]['pre_tax']);
    }

    public function testComputeSummaryVatWithholdingAndPayable(): void
    {
        $result = compute_invoice_totals([
            'currency' => 'บาท',
            'language' => 'th',
            'withholding_rate' => '3',
            'items' => [
                ['qty' => '2', 'price' => '6000', 'discount' => '20', 'vat' => '7'],
                ['qty' => '1', 'price' => '5000', 'discount' => '0', 'vat' => '7'],
            ],
        ]);

        $summary = $result['summary'];
        $this->assertSame('14,600.00 บาท', $summary['taxable_amount']); // 9600 + 5000
        $this->assertSame('1,022.00 บาท', $summary['vat_amount']);      // 7% of 14,600
        $this->assertSame('15,622.00 บาท', $summary['total_amount']);   // taxable + vat
        $this->assertSame('438.00 บาท', $summary['withholding']);       // 3% of 14,600
        $this->assertSame('15,184.00 บาท', $summary['payable']);        // total - withholding
        $this->assertSame('หนึ่งหมื่นห้าพันหกร้อยยี่สิบสองบาทถ้วน', $summary['total_words']);
    }

    public function testComputeLeavesSummaryBlankWithoutItemsOrStoredSummary(): void
    {
        $result = compute_invoice_totals(['currency' => 'บาท', 'items' => []]);

        foreach ($result['summary'] as $value) {
            $this->assertSame('', $value);
        }
    }

    public function testComputePreservesManualSummaryWhenNoItems(): void
    {
        // Legacy invoices predate auto-calc: they carry a manual summary and no
        // line items. Those totals must survive rather than being blanked.
        $result = compute_invoice_totals([
            'items' => [],
            'summary' => ['total_amount' => '43,500.00 บาท', 'payable' => '43,500.00 บาท'],
        ]);

        $this->assertSame('43,500.00 บาท', $result['summary']['total_amount']);
        $this->assertSame('43,500.00 บาท', $result['summary']['payable']);
        $this->assertSame('', $result['summary']['vat_amount']); // still-present key stays blank
    }

    // ---- integration via sanitize_invoice -------------------------------

    public function testSanitizeComputesPreTaxAndSummaryFromRawInput(): void
    {
        $result = sanitize_invoice([
            'currency' => 'บาท',
            'withholding_rate' => '0',
            'items' => [
                ['name' => 'Table', 'qty' => '2', 'price' => '6000', 'discount' => '20', 'vat' => '7', 'pre_tax' => 'ignored'],
            ],
        ]);

        // pre_tax is always recomputed, never trusted from the client.
        $this->assertSame('9,600.00', $result['items'][0]['pre_tax']);
        $this->assertSame('9,600.00 บาท', $result['summary']['taxable_amount']);
        $this->assertSame('672.00 บาท', $result['summary']['vat_amount']);
        $this->assertSame('10,272.00 บาท', $result['summary']['total_amount']);
    }

    public function testSanitizeDefaultsSettingsFields(): void
    {
        $result = sanitize_invoice([]);
        $this->assertSame('th', $result['language']);
        $this->assertSame('', $result['currency']);
        $this->assertSame('0', $result['withholding_rate']);
        $this->assertSame('', $result['seller']['logo_url']);
    }

    public function testSanitizeNormalizesLanguageToThaiOrEnglish(): void
    {
        $this->assertSame('en', sanitize_invoice(['language' => 'en'])['language']);
        $this->assertSame('th', sanitize_invoice(['language' => 'fr'])['language']);
    }
}
