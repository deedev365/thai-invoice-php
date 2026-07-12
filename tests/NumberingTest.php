<?php

use PHPUnit\Framework\TestCase;

/**
 * Verifies document-number generation and file identity, which the API
 * relies on when creating new invoices. Uses an isolated temp storage dir.
 */
final class NumberingTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'invoice_test_' . uniqid();
        mkdir($this->storageDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storageDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->storageDir);
    }

    private function writeInvoice(string $filename, array $document): void
    {
        file_put_contents(
            $this->storageDir . DIRECTORY_SEPARATOR . $filename,
            json_encode(['document' => $document])
        );
    }

    public function testFirstNumberForEmptyStorage(): void
    {
        $this->assertSame('DL-260712001', next_document_number($this->storageDir, '12/07/2026'));
    }

    public function testSequenceIncrementsForSameDate(): void
    {
        $this->writeInvoice('invoice-260712001.json', [
            'number' => 'DL-260712001',
            'issue_date' => '12/07/2026',
        ]);

        $this->assertSame('DL-260712002', next_document_number($this->storageDir, '12/07/2026'));
    }

    public function testSequenceIsPerDate(): void
    {
        $this->writeInvoice('invoice-260712001.json', [
            'number' => 'DL-260712001',
            'issue_date' => '12/07/2026',
        ]);

        // A different date starts its own sequence at 001.
        $this->assertSame('DL-260713001', next_document_number($this->storageDir, '13/07/2026'));
    }

    public function testNextIdentityShape(): void
    {
        $identity = next_invoice_identity($this->storageDir, '12/07/2026');

        $this->assertSame('DL-260712001', $identity['number']);
        $this->assertSame('invoice-260712001.json', $identity['filename']);
        $this->assertSame('invoice/invoice-260712001.json', $identity['key']);
        $this->assertStringEndsWith('invoice-260712001.json', $identity['path']);
    }

    public function testExcludePathIgnoresSelfWhenRenumbering(): void
    {
        $this->writeInvoice('invoice-260712001.json', [
            'number' => 'DL-260712001',
            'issue_date' => '12/07/2026',
        ]);
        $self = $this->storageDir . DIRECTORY_SEPARATOR . 'invoice-260712001.json';

        // Excluding the file itself (an update) reuses its own slot.
        $this->assertSame('DL-260712001', next_document_number($this->storageDir, '12/07/2026', $self));
    }

    public function testDefaultDataFileIsNotCountedAsSaved(): void
    {
        // A stray invoice_data.json in storage must be ignored by the counter.
        file_put_contents(
            $this->storageDir . DIRECTORY_SEPARATOR . 'invoice_data.json',
            json_encode(['document' => ['number' => 'DL-260712009', 'issue_date' => '12/07/2026']])
        );

        $this->assertSame('DL-260712001', next_document_number($this->storageDir, '12/07/2026'));
    }
}
