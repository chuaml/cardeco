<?php

declare(strict_types=1);

namespace test\Integration;

use StockManager;
use test\Support\DatabaseTestCase;

/**
 * Characterization of StockManager (src/Legacy/StockManager.php), the paginated search behind /liststock.
 * The encoded result rows are protected, so the tests read them through a tiny anonymous subclass.
 */
final class StockManagerTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;
    /** @var array<string, int> item code => stock_items.id */
    private $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $this->ids[$this->code('A')] = $this->insertStockItem($this->code('A'), 'ALPHA <b>&"\'</b>', 5, 'SET', 'GROUP A');
        $this->ids[$this->code('B')] = $this->insertStockItem($this->code('B'), 'BRAVO DESCWORD', 0, 'PCS', 'GROUP B');
        $this->ids[$this->code('C')] = $this->insertStockItem($this->code('C'), 'CHARLIE', 12, 'UNIT', 'GROUP C');
    }

    private function code(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    private function prefix(): string
    {
        return self::FIXTURE_PREFIX . $this->suffix;
    }

    /** @return StockManager with a public rows() accessor for the protected, encoded result */
    private function manager(): StockManager
    {
        return new class($this->con) extends StockManager {
            /** @return array<int, array<string, mixed>> */
            public function rows(): array
            {
                return $this->result;
            }
        };
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function codesOf(array $rows): array
    {
        $codes = array_column($rows, 'item_code');
        sort($codes);

        return $codes;
    }

    // ------------------------------------------------------------------ constructor

    public function testConstructor_CountsAllStockItemsIncludingTheOnesJustInserted(): void
    {
        $before = (new StockManager($this->con))->getTotal_num_stock();
        $this->insertStockItem($this->code('D'), 'DELTA', 1);
        $this->insertStockItem($this->code('E'), 'ECHO', 1);

        $after = (new StockManager($this->con))->getTotal_num_stock();

        $this->assertIsInt($before);
        $this->assertSame($before + 2, $after, 'COUNT(id) FROM stock_items runs in the constructor');
    }

    public function testConstructor_StartsWithNoResultAndNoPages(): void
    {
        $sm = $this->manager();

        $this->assertSame([], $sm->rows());
        $this->assertSame([], $sm->getResult_pages());
        $this->assertSame(0, $sm->query_num_rows);
        $this->assertStringContainsString('<p>No Results...</p>', $sm->getResult());
    }

    // ------------------------------------------------------------------ search

    public function testExecuteQuery_SearchTerm_ReturnsMatchingItemsWithQuantity(): void
    {
        $sm = $this->manager();
        $sm->setQueryArg($this->prefix());
        $sm->execute_query(1, 0, false);

        $this->assertSame(3, $sm->query_num_rows);
        $this->assertSame([1], $sm->getResult_pages(), '3 rows / default limit 500 = 1 page');
        $rows = $sm->rows();
        $this->assertSame([$this->code('A'), $this->code('B'), $this->code('C')], $this->codesOf($rows));
        $byCode = array_column($rows, null, 'item_code');
        $this->assertSame(
            ['id', 'item_code', 'description', 'uom', 'item_group', 'quantity', 'image'],
            array_keys($byCode[$this->code('C')])
        );
        $this->assertSame((string) $this->ids[$this->code('C')], $byCode[$this->code('C')]['id']);
        $this->assertSame('CHARLIE', $byCode[$this->code('C')]['description']);
        $this->assertSame('UNIT', $byCode[$this->code('C')]['uom']);
        $this->assertSame('GROUP C', $byCode[$this->code('C')]['item_group']);
        $this->assertSame('12', $byCode[$this->code('C')]['quantity'], 'quantity is returned as a string (mysqli_fetch_assoc)');
        $this->assertSame('0', $byCode[$this->code('B')]['quantity']);
    }

    public function testExecuteQuery_SearchTermIsSplitOnSpaces_AndEveryWordMustMatchCodeOrDescription(): void
    {
        $sm = $this->manager();
        $sm->setQueryArg('  ' . $this->suffix . '   descword ');
        $sm->execute_query(1, 0, false);

        $this->assertSame(1, $sm->query_num_rows, 'word 1 matches the item code, word 2 the description (case-insensitive LIKE)');
        $this->assertSame([$this->code('B')], $this->codesOf($sm->rows()));
    }

    public function testExecuteQuery_NoMatch_YieldsEmptyResultAndNoPages(): void
    {
        $sm = $this->manager();
        $sm->setQueryArg($this->prefix() . '-NOPE');
        $sm->execute_query(1, 0, false);

        $this->assertSame(0, $sm->query_num_rows);
        $this->assertSame([], $sm->rows());
        $this->assertSame([], $sm->getResult_pages());
        $this->assertSame('<table id="liststock"><p>No Results...</p></table>', $sm->getResult());
    }

    public function testExecuteQuery_SearchTermWithQuote_IsEscapedNotInjected(): void
    {
        $sm = $this->manager();
        $sm->setQueryArg($this->prefix() . "' OR '1'='1");
        $sm->execute_query(1, 0, false);

        $this->assertSame(0, $sm->query_num_rows, 'mysqli_real_escape_string keeps the quote literal (StockManager.php:144)');
    }

    // ------------------------------------------------------------------ pagination

    public function testExecuteQuery_LimitAndPage_SliceTheResultAndListAllPages(): void
    {
        $page1 = $this->manager();
        $page1->setQueryArg($this->prefix());
        $page1->execute_query(1, 2, false);

        $page2 = $this->manager();
        $page2->setQueryArg($this->prefix());
        $page2->execute_query(2, 2, false);

        $this->assertSame(3, $page1->query_num_rows, 'the count is the full match count, not the page size');
        $this->assertSame([1, 2], $page1->getResult_pages());
        $this->assertCount(2, $page1->rows());
        $this->assertCount(1, $page2->rows());
        $this->assertSame([1, 2], $page2->getResult_pages());
        $this->assertSame(
            [$this->code('A'), $this->code('B'), $this->code('C')],
            $this->codesOf(array_merge($page1->rows(), $page2->rows())),
            'the two pages partition the result set'
        );
    }

    public function testExecuteQuery_PageBeyondTheEnd_ReturnsNoRowsButKeepsTheCount(): void
    {
        $sm = $this->manager();
        $sm->setQueryArg($this->prefix());
        $sm->execute_query(5, 2, false);

        $this->assertSame(3, $sm->query_num_rows);
        $this->assertSame([], $sm->rows());
    }

    // ------------------------------------------------------------------ encoding / HTML

    public function testGetResult_RendersEscapedCellsEditLinkAndBookmarkCheckbox(): void
    {
        $sm = $this->manager();
        $sm->setQueryArg($this->code('A'));
        $sm->execute_query(1, 0, false);

        $html = $sm->getResult();
        $id = $this->ids[$this->code('A')];

        $this->assertStringStartsWith('<table id="liststock"><thead><tr><th>id<span></span></th><th>item_code<span></span></th>', $html);
        $this->assertStringContainsString('<th>Modify</th><th onclick="setAllCheckboxes(this)">mark</th></tr></thead>', $html);
        $this->assertStringContainsString(
            '<tr><td>' . $id . '</td><td>' . $this->code('A') . '</td><td>ALPHA &lt;b&gt;&amp;&quot;&#039;&lt;/b&gt;</td><td>SET</td><td>GROUP A</td><td>5</td><td>0</td>'
            . '<td><a href="itemdetail?id=' . $id . '">Edit</a></td>'
            . '<td><input type="checkbox" class="checkbox" ROW="0" name="row[0][id]" value="' . $id . '"/></td></tr>',
            $html,
            'htmlspecialchars(ENT_QUOTES) on every cell; rows are NOT wrapped in <tbody>'
        );
        $this->assertStringEndsWith('</table>', $html);
    }

    public function testExecuteQuery_WithoutImages_ImageColumnBecomesTheCountOfLegacyImageIds(): void
    {
        $stmt = $this->con->prepare('UPDATE stock_items SET image = ? WHERE id = ?');
        $csv = '12,13,14';
        $stmt->bind_param('si', $csv, $this->ids[$this->code('B')]);
        $stmt->execute();
        $stmt->close();

        $sm = $this->manager();
        $sm->setQueryArg($this->prefix());
        $sm->execute_query(1, 0, false);

        $byCode = array_column($sm->rows(), null, 'item_code');
        $this->assertSame(3, $byCode[$this->code('B')]['image'], 'stock_items.image CSV "12,13,14" -> 3');
        $this->assertSame(0, $byCode[$this->code('A')]['image'], 'empty CSV -> 0');
    }

    public function testExecuteQuery_WithImages_GroupsByItemAndRendersImgTags(): void
    {
        $stmt = $this->con->prepare('INSERT INTO stock_images (directory, image, description, upload_name, file_type, item) VALUES (?, ?, \'ZZTEST\', \'ZZTEST\', \'jpg\', ?)');
        $itemCsv = (string) $this->ids[$this->code('A')];
        $dir = 'ZZTEST-dir/';
        $img1 = 'ZZTEST-1.jpg';
        $stmt->bind_param('sss', $dir, $img1, $itemCsv);
        $stmt->execute();
        $img2 = 'ZZTEST-2.jpg';
        $stmt->bind_param('sss', $dir, $img2, $itemCsv);
        $stmt->execute();
        $stmt->close();

        $sm = $this->manager();
        $sm->setQueryArg($this->prefix());
        $sm->execute_query(1, 0, true); // GROUP BY stock_items.id + GROUP_CONCAT(images) - must satisfy ONLY_FULL_GROUP_BY

        $this->assertSame(3, $sm->query_num_rows);
        $byCode = array_column($sm->rows(), null, 'item_code');
        $this->assertSame([$this->code('A'), $this->code('B'), $this->code('C')], $this->codesOf($sm->rows()), 'one row per item despite two images');
        $image = $byCode[$this->code('A')]['image'];
        $this->assertStringStartsWith('<div class="item_images">', $image);
        $this->assertStringEndsWith('</div>', $image);
        $this->assertStringContainsString('<img src="ZZTEST-dir/ZZTEST-1.jpg" class="liststock" alt="image" />', $image);
        $this->assertStringContainsString('<img src="ZZTEST-dir/ZZTEST-2.jpg" class="liststock" alt="image" />', $image);
        $this->assertSame(2, substr_count($image, '<img '), 'GROUP_CONCAT joined both images with the || delimiter');
        $this->assertNull($byCode[$this->code('B')]['image'], 'no images -> GROUP_CONCAT NULL is kept as-is');
        $this->assertSame('5', $byCode[$this->code('A')]['quantity']);
    }
}
