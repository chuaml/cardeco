<?php

declare(strict_types=1);

namespace test\Integration;

use StockReport;
use test\Support\DatabaseTestCase;

/**
 * Characterization of StockReport (src/Legacy/StockReport.php), the /stockreport page: lines pasted from Excel
 * ("china code <TAB> china name <TAB> item code") -> current quantity per item + the lines that match nothing.
 */
final class StockReportTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    private function code(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    public function testExecuteQuery_KnownAndUnknownItemCodes_AreSplitIntoResultAndNotFound(): void
    {
        $this->insertStockItem($this->code('A'), 'ALPHA', 7);
        $report = new StockReport($this->con);

        $report->setQueryArg(
            "ZZ-CN-1\tzz name one\t" . $this->code('A') . "\r\n"
            . "ZZ-CN-2\tzz name two\t" . $this->code('MISSING')
        );
        $report->execute_query();

        $this->assertStringContainsString(
            '<tr><td>ZZ-CN-1</td><td>zz name one</td><td>' . $this->code('A') . '</td><td>ALPHA</td><td>7</td>',
            $report->getResult()
        );
        $notFound = $report->getItems_notfound();
        $this->assertStringStartsWith('1<table', $notFound);
        $this->assertStringContainsString(
            '<tr><td>ZZ-CN-2</td><td>zz name two</td><td>' . $this->code('MISSING') . '</td></tr>',
            $notFound
        );
    }

    public function testSetQueryArg_LineWithFewerThanThreeColumns_IsListedAsNotFoundWithAnEmptyItemCode(): void
    {
        // the third column is missing, so item_code is null: strlen(null) is deprecated since PHP 8.1 and must not
        // surface (deprecations fail this suite); the line simply ends up in "Items Not Found"
        $this->insertStockItem($this->code('A'), 'ALPHA', 7);
        $report = new StockReport($this->con);

        $report->setQueryArg(
            "ZZ-CN-1\tzz name one\t" . $this->code('A') . "\r\n"
            . "ZZ-CN-2\tzz only two columns"
        );
        $report->execute_query();

        $this->assertStringContainsString('<td>' . $this->code('A') . '</td><td>ALPHA</td><td>7</td>', $report->getResult());
        $notFound = $report->getItems_notfound();
        $this->assertStringStartsWith('1<table', $notFound);
        $this->assertStringContainsString('<tr><td>ZZ-CN-2</td><td>zz only two columns</td><td></td></tr>', $notFound);
    }
}
