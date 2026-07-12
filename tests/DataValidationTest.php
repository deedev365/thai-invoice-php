<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validates how the API cleans and normalizes incoming invoice data,
 * i.e. the contract enforced by lib.php before anything is stored or served.
 */
final class DataValidationTest extends TestCase
{
    // ---- sanitize_invoice: string cleaning & structure ------------------

    public function testSanitizeTrimsAndFillsMissingSections(): void
    {
        $result = sanitize_invoice([
            'document' => ['title' => '  Hello  '],
            'seller' => ['name' => " ACME \n"],
        ]);

        $this->assertSame('Hello', $result['document']['title']);
        $this->assertSame('ACME', $result['seller']['name']);
        // Missing sections are still present with empty defaults.
        $this->assertSame('', $result['customer']['email']);
        $this->assertSame('', $result['contact']['line_id']);
        $this->assertSame('', $result['summary']['payable']);
        $this->assertSame('', $result['note']);
        $this->assertSame([], $result['items']);
        $this->assertSame([], $result['banks']);
    }

    public function testSanitizeAcceptsNonArrayInput(): void
    {
        $result = sanitize_invoice('garbage');
        $this->assertSame('', $result['document']['title']);
        $this->assertSame([], $result['items']);
    }

    // ---- sanitize_invoice: items ----------------------------------------

    public function testSanitizeKeepsOnlyCanonicalItemFields(): void
    {
        $result = sanitize_invoice([
            'items' => [
                ['name' => 'Sofa', 'qty' => '2', 'price' => '100', 'pre_tax' => '200', 'cost' => '999', 'hacker' => 'x'],
            ],
        ]);

        $item = $result['items'][0];
        $this->assertSame(
            ['name', 'detail', 'qty', 'price', 'discount', 'vat', 'pre_tax'],
            array_keys($item)
        );
        $this->assertArrayNotHasKey('cost', $item);
        $this->assertArrayNotHasKey('hacker', $item);
    }

    public function testSanitizeDropsEmptyItemsButKeepsMeaningfulOnes(): void
    {
        $result = sanitize_invoice([
            'items' => [
                ['name' => '', 'detail' => '', 'qty' => '', 'price' => '', 'discount' => '', 'vat' => '', 'pre_tax' => ''],
                ['vat' => '7'], // vat alone is not "content" -> dropped
                ['name' => 'Real item'],
            ],
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Real item', $result['items'][0]['name']);
    }

    public function testSanitizeIgnoresNonArrayItemEntries(): void
    {
        $result = sanitize_invoice(['items' => ['not-an-array', ['name' => 'Ok']]]);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Ok', $result['items'][0]['name']);
    }

    // ---- sanitize_invoice: banks ----------------------------------------

    public function testSanitizeBankDefaultsLogoClassToOther(): void
    {
        $result = sanitize_invoice([
            'banks' => [['logo_class' => '', 'bank_name' => 'Test Bank']],
        ]);

        $this->assertSame('other', $result['banks'][0]['logo_class']);
        $this->assertSame('Test Bank', $result['banks'][0]['bank_name']);
    }

    public function testSanitizeDropsEmptyBanks(): void
    {
        $result = sanitize_invoice([
            'banks' => [
                ['logo_class' => 'kbank'], // only logo fields -> dropped
                ['bank_name' => 'Keep me'],
            ],
        ]);

        $this->assertCount(1, $result['banks']);
        $this->assertSame('Keep me', $result['banks'][0]['bank_name']);
    }

    // ---- sanitize_invoice: document dates -------------------------------

    public function testSanitizeNormalizesDocumentDates(): void
    {
        $result = sanitize_invoice([
            'document' => ['issue_date' => '03/05/2026', 'credit' => 'net 7 days'],
        ]);

        $this->assertSame('03/05/2026', $result['document']['issue_date']);
        $this->assertSame('7', $result['document']['credit']); // extracted & stringified
        $this->assertSame('10/05/2026', $result['document']['due_date']); // +7 days
    }

    // ---- date helpers ---------------------------------------------------

    public function testParseInvoiceDateValid(): void
    {
        $date = parse_invoice_date('15/08/2026');
        $this->assertSame('15/08/2026', $date->format('d/m/Y'));
    }

    public function testParseInvoiceDateInvalidFallsBackToToday(): void
    {
        $today = (new DateTimeImmutable('today'))->format('d/m/Y');
        $this->assertSame($today, parse_invoice_date('31/02/2026')->format('d/m/Y'));
        $this->assertSame($today, parse_invoice_date('not a date')->format('d/m/Y'));
    }

    #[DataProvider('creditDaysProvider')]
    public function testCreditDays(string $input, int $expected): void
    {
        $this->assertSame($expected, credit_days($input));
    }

    public static function creditDaysProvider(): array
    {
        return [
            'plain number' => ['7', 7],
            'embedded' => ['net 30 days', 30],
            'negative clamped' => ['-5', 0],
            'non numeric' => ['abc', 0],
            'empty' => ['', 0],
        ];
    }

    public function testCalculateDueDate(): void
    {
        $this->assertSame('10/05/2026', calculate_due_date('03/05/2026', '7'));
        $this->assertSame('03/05/2026', calculate_due_date('03/05/2026', '0'));
    }

    // ---- normalize_data_key: path-traversal safety ----------------------

    #[DataProvider('dataKeyProvider')]
    public function testNormalizeDataKeyResolvesSafely(string $input, string $expectedKey): void
    {
        $result = normalize_data_key($input, '/app/invoice_data.json', '/app/invoice');
        $this->assertSame($expectedKey, $result['key']);
    }

    public static function dataKeyProvider(): array
    {
        return [
            'empty -> default' => ['', 'invoice_data.json'],
            'default name' => ['invoice_data.json', 'invoice_data.json'],
            'valid saved file' => ['invoice/invoice-260503001.json', 'invoice/invoice-260503001.json'],
            'parent traversal blocked' => ['../../etc/passwd', 'invoice_data.json'],
            'slash traversal blocked' => ['invoice/../../secret.json', 'invoice_data.json'],
            'backslash traversal blocked' => ['invoice\\..\\..\\secret.json', 'invoice_data.json'],
            'non-json blocked' => ['invoice/evil.php', 'invoice_data.json'],
        ];
    }

    public function testNormalizeDataKeyBuildsStoragePath(): void
    {
        $result = normalize_data_key('invoice/invoice-260503001.json', '/app/invoice_data.json', '/app/invoice');
        $this->assertStringEndsWith('invoice-260503001.json', $result['path']);
        $this->assertStringContainsString('invoice', $result['path']);
    }

    // ---- prepare_invoice_for_view: labels & structure -------------------

    public function testPrepareMergesDefaultTranslations(): void
    {
        $invoice = prepare_invoice_for_view(['document' => []], 'invoice/x.json', sys_get_temp_dir());

        // Every label the printed page reads must exist, including the
        // previously-missing "payment" key.
        $this->assertSame('Payment', $invoice['translate']['payment']);
        $this->assertSame('Seller', $invoice['translate']['seller']);
        $this->assertArrayHasKey('amount_payable', $invoice['translate']);
    }

    public function testPrepareKeepsCustomTranslationsOverDefaults(): void
    {
        $invoice = prepare_invoice_for_view(
            ['translate' => ['seller' => 'ผู้ขาย']],
            'invoice/x.json',
            sys_get_temp_dir()
        );
        $this->assertSame('ผู้ขาย', $invoice['translate']['seller']);
        $this->assertSame('Payment', $invoice['translate']['payment']); // default still filled
    }

    public function testPrepareFillsAllSections(): void
    {
        $invoice = prepare_invoice_for_view([], 'invoice/x.json', sys_get_temp_dir());
        foreach (['document', 'seller', 'customer', 'contact', 'summary'] as $section) {
            $this->assertArrayHasKey($section, $invoice);
        }
        $this->assertIsArray($invoice['items']);
        $this->assertIsArray($invoice['banks']);
        $this->assertIsString($invoice['note']);
    }

    public function testPrepareSeedsBlankTemplateWithTodayAndNumber(): void
    {
        $invoice = prepare_invoice_for_view([], 'invoice_data.json', sys_get_temp_dir());

        $today = (new DateTimeImmutable('today'))->format('d/m/Y');
        $this->assertSame($today, $invoice['document']['issue_date']);
        $this->assertMatchesRegularExpression('/^DL-\d{9}$/', $invoice['document']['number']);
    }

    public function testPrepareDoesNotOverrideDatesForSavedInvoice(): void
    {
        $invoice = prepare_invoice_for_view(
            ['document' => ['issue_date' => '03/05/2026', 'credit' => '7', 'number' => 'DL-260503001']],
            'invoice/invoice-260503001.json',
            sys_get_temp_dir()
        );
        $this->assertSame('03/05/2026', $invoice['document']['issue_date']);
        $this->assertSame('10/05/2026', $invoice['document']['due_date']);
        $this->assertSame('DL-260503001', $invoice['document']['number']);
    }

    // ---- default_translations -------------------------------------------

    public function testDefaultTranslationsCoverAllPrintedLabels(): void
    {
        $keys = array_keys(default_translations());
        foreach (['seller', 'number', 'items_description', 'taxable_amount_7', 'payment', 'amount_payable'] as $expected) {
            $this->assertContains($expected, $keys);
        }
    }
}
