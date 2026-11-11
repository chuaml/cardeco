<?php

declare(strict_types=1);

namespace test\Integration;

use Bookmark;
use Page_Handler_Bookmark;
use StockBookmark;
use test\Support\DatabaseTestCase;

/**
 * Characterization of the bookmark trio behind /bookmarks and /bookmarks_modify:
 *   Bookmark             (src/Legacy/Bookmark.php)             - CRUD on bookmarks / bookmarks_items
 *   StockBookmark        (src/Legacy/StockBookmark.php)        - paginated listing of one bookmark's items
 *   Page_Handler_Bookmark (src/Legacy/Page_Handler_Bookmark.php) - bookmark list as <a> links
 *
 * Bookmark's methods return null on success and `false` (after trigger_error) on failure.
 */
final class BookmarkTest extends DatabaseTestCase
{
    /** @var string */
    private $suffix;
    /** @var Bookmark */
    private $bookmark;
    /** @var int */
    private $itemA;
    /** @var int */
    private $itemB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $this->bookmark = new Bookmark($this->con);
        $this->itemA = $this->insertStockItem($this->code('A'), 'ALPHA <b>x</b>', 5, 'SET', 'GROUP A');
        $this->itemB = $this->insertStockItem($this->code('B'), 'BRAVO', 0, 'PCS', 'GROUP B');
    }

    private function code(string $letter): string
    {
        return self::FIXTURE_PREFIX . $this->suffix . '-' . $letter;
    }

    private function name(string $n = ''): string
    {
        return self::FIXTURE_PREFIX . 'BM-' . $this->suffix . $n;
    }

    /** Creates a bookmark through the class under test and returns its id. */
    private function createBookmark(string $name): int
    {
        $this->assertNull($this->bookmark->createNew_bookmark($name));
        $rows = $this->fetchAll('SELECT id FROM bookmarks WHERE name = ?', 's', $name);
        $this->assertCount(1, $rows);

        return (int) $rows[0]['id'];
    }

    /** @return array<int, array<string, mixed>> bookmarks_items rows of a bookmark ordered by item_id */
    private function itemsOf(int $bookmarkId): array
    {
        return $this->fetchAll('SELECT item_id, status, date FROM bookmarks_items WHERE bookmark_id = ? ORDER BY item_id', 'i', $bookmarkId);
    }

    private function today(): string
    {
        return $this->fetchAll('SELECT CURDATE() AS d')[0]['d'];
    }

    /** @return array<string, string> name => id of the ZZTEST- bookmarks in a getBookmarks() result */
    private function mine(array $bookmarks): array
    {
        $mine = [];
        foreach ($bookmarks as $row) {
            if (strpos($row['name'], $this->name()) === 0) {
                $mine[$row['name']] = $row['id'];
            }
        }

        return $mine;
    }

    // ------------------------------------------------------------------ Bookmark: create / list / status

    public function testCreateNewBookmark_InsertsActiveBookmarkDatedToday(): void
    {
        $this->assertNull($this->bookmark->createNew_bookmark('  ' . $this->name() . '  '));

        $rows = $this->fetchAll('SELECT name, date, status FROM bookmarks WHERE name = ?', 's', $this->name());
        $this->assertSame([['name' => $this->name(), 'date' => $this->today(), 'status' => 1]], $rows, 'name is trimmed');
    }

    public function testCreateNewBookmark_ExistingName_ReturnsFalseAndDoesNotInsertAgain(): void
    {
        $this->createBookmark($this->name());

        $result = @$this->bookmark->createNew_bookmark($this->name()); // trigger_error('Bookmark Name exist.') + false

        $this->assertFalse($result);
        $this->assertCount(1, $this->fetchAll('SELECT id FROM bookmarks WHERE name = ?', 's', $this->name()));
    }

    public function testGetBookmarks_ListsActiveOnesByDefaultAndInactiveOnesOnRequest(): void
    {
        $active = $this->createBookmark($this->name('-ACTIVE'));
        $inactive = $this->createBookmark($this->name('-INACTIVE'));
        $this->con->query('UPDATE bookmarks SET status = 0 WHERE id = ' . $inactive);

        $this->assertSame([$this->name('-ACTIVE') => (string) $active], $this->mine($this->bookmark->getBookmarks()));
        $this->assertSame([$this->name('-INACTIVE') => (string) $inactive], $this->mine($this->bookmark->getBookmarks(0)));
        $this->assertSame(['id', 'name'], array_keys($this->bookmark->getBookmarks()[0]));
    }

    public function testUpdateBookmarks_SoftRemovesAndRestoresByStatus(): void
    {
        $one = $this->createBookmark($this->name('-1'));
        $two = $this->createBookmark($this->name('-2'));
        $three = $this->createBookmark($this->name('-3'));

        $this->assertNull($this->bookmark->updateBookmarks([(string) $one, ' ', (string) $two], 0)); // $_POST['chkBookmarks'] shape

        $this->assertSame([$this->name('-3') => (string) $three], $this->mine($this->bookmark->getBookmarks()));
        $removed = $this->mine($this->bookmark->getBookmarks(0));
        ksort($removed);
        $this->assertSame([$this->name('-1') => (string) $one, $this->name('-2') => (string) $two], $removed);

        $this->assertNull($this->bookmark->updateBookmarks([(string) $one], 1));

        $restored = $this->mine($this->bookmark->getBookmarks());
        ksort($restored);
        $this->assertSame([$this->name('-1') => (string) $one, $this->name('-3') => (string) $three], $restored);
    }

    // ------------------------------------------------------------------ Bookmark: items

    public function testSetBookmarksItems_InsertsCheckedStockItemsOnceAndSkipsRowsWithoutId(): void
    {
        $id = $this->createBookmark($this->name());

        $this->assertNull($this->bookmark->setBookmarks_items($id, [
            ['id' => (string) $this->itemA],
            ['id' => ' '],                      // blank -> skipped
            ['comment' => 'no id key'],         // skipped
            ['id' => (string) $this->itemB],
        ]));

        $this->assertSame(
            [
                ['item_id' => $this->itemA, 'status' => 1, 'date' => $this->today()],
                ['item_id' => $this->itemB, 'status' => 1, 'date' => $this->today()],
            ],
            $this->itemsOf($id)
        );
    }

    public function testSetBookmarksItems_RepeatedForTheSameItems_DoesNotDuplicateRows(): void
    {
        $id = $this->createBookmark($this->name());
        $this->bookmark->setBookmarks_items($id, [['id' => (string) $this->itemA]]);

        $this->bookmark->setBookmarks_items($id, [['id' => (string) $this->itemA], ['id' => (string) $this->itemB]]);

        $this->assertSame([$this->itemA, $this->itemB], array_column($this->itemsOf($id), 'item_id'));
    }

    public function testRemoveBookmarksItems_SoftDeletes_AndSetBookmarksItemsReactivates(): void
    {
        $id = $this->createBookmark($this->name());
        $this->bookmark->setBookmarks_items($id, [['id' => (string) $this->itemA], ['id' => (string) $this->itemB]]);

        $this->assertNull($this->bookmark->removeBookmarks_items($id, [['row_id' => (string) $this->itemA]], 'row_id'));
        $this->assertSame([0, 1], array_column($this->itemsOf($id), 'status'), 'A inactive, B untouched; the row is kept');

        $this->assertNull($this->bookmark->setBookmarks_items($id, [['id' => (string) $this->itemA]]));
        $this->assertSame([1, 1], array_column($this->itemsOf($id), 'status'), 're-adding flips status back to 1 instead of inserting');
        $this->assertCount(2, $this->itemsOf($id));
    }

    public function testRemoveBookmarksItems_NothingSelected_EchoesMessageAndReturnsFalse(): void
    {
        $id = $this->createBookmark($this->name());

        ob_start();
        try {
            $result = $this->bookmark->removeBookmarks_items($id, [['id' => '']], 'id');
        } finally {
            $output = ob_get_clean();
        }

        $this->assertFalse($result);
        $this->assertSame('No selected Items for removing', $output, 'Bookmark.php:178 prints straight to the response');
    }

    // ------------------------------------------------------------------ StockBookmark

    /** @return StockBookmark with a public rows() accessor for the protected, encoded result */
    private function stockBookmark(): StockBookmark
    {
        return new class($this->con) extends StockBookmark {
            /** @return array<int, array<string, mixed>> */
            public function rows(): array
            {
                return $this->result;
            }
        };
    }

    public function testStockBookmark_ListsActiveItemsOfTheBookmarkOnly(): void
    {
        $id = $this->createBookmark($this->name());
        $other = $this->createBookmark($this->name('-OTHER'));
        $this->bookmark->setBookmarks_items($id, [['id' => (string) $this->itemA], ['id' => (string) $this->itemB]]);
        $this->bookmark->setBookmarks_items($other, [['id' => (string) $this->itemB]]);
        $this->bookmark->removeBookmarks_items($id, [['id' => (string) $this->itemB]], 'id');

        $sb = $this->stockBookmark();
        $sb->setBookmark_id($id);
        $sb->setQueryArg('');
        $sb->execute_query(1); // GROUP BY ... HAVING bookmarks.id = N (StockBookmark.php:97-104) under ONLY_FULL_GROUP_BY

        $this->assertSame(1, $sb->query_num_rows);
        $this->assertSame([1], $sb->getResult_pages());
        $this->assertSame(
            [[
                'id' => (string) $this->itemA,
                'item_code' => $this->code('A'),
                'description' => 'ALPHA &lt;b&gt;x&lt;/b&gt;',
                'uom' => 'SET',
                'item_group' => 'GROUP A',
                'image' => null,
            ]],
            $sb->rows(),
            '`SELECT bookmarks.id, stock_items.id` collapses to one `id` key = the STOCK ITEM id (last column wins)'
        );
    }

    public function testStockBookmark_SearchTermFiltersWithinTheBookmark(): void
    {
        $id = $this->createBookmark($this->name());
        $this->bookmark->setBookmarks_items($id, [['id' => (string) $this->itemA], ['id' => (string) $this->itemB]]);

        $sb = $this->stockBookmark();
        $sb->setBookmark_id($id);
        $sb->setQueryArg('bravo');
        $sb->execute_query(1);

        $this->assertSame(1, $sb->query_num_rows);
        $this->assertSame([$this->code('B')], array_column($sb->rows(), 'item_code'));
    }

    public function testStockBookmark_GetResult_RendersRemarkInputAndCheckboxPerRow(): void
    {
        $id = $this->createBookmark($this->name());
        $this->bookmark->setBookmarks_items($id, [['id' => (string) $this->itemA]]);

        $sb = $this->stockBookmark();
        $sb->setBookmark_id($id);
        $sb->setQueryArg('');
        $sb->execute_query(1);
        $html = $sb->getResult();

        $this->assertStringStartsWith('<table id="liststock"><thead><tr><th>id<span></span></th><th>item_code<span></span></th>', $html);
        $this->assertStringContainsString('<th>Remark</th><th onclick="setAllCheckboxes(this)">Select</th></tr></thead>', $html);
        $this->assertStringContainsString(
            '<tr><td>' . $this->itemA . '</td><td>' . $this->code('A') . '</td><td>ALPHA &lt;b&gt;x&lt;/b&gt;</td><td>SET</td><td>GROUP A</td><td></td>'
            . '<td><input type="text" class="comment" minlength="1" maxlength="255" placeholder="remarks..." name="row[0][comment]" /></td>'
            . '<td><input type="checkbox" class="checkbox" ROW="0" name="row[0][id]" value="' . $this->itemA . '"/></td></tr>',
            $html
        );
    }

    public function testStockBookmark_EmptyBookmark_RendersPlaceholder(): void
    {
        $id = $this->createBookmark($this->name());

        $sb = $this->stockBookmark();
        $sb->setBookmark_id($id);
        $sb->setQueryArg('');
        $sb->execute_query(1);

        $this->assertSame(0, $sb->query_num_rows);
        $this->assertSame('<table id="liststock"><p>No bookmark items...</p></table>', $sb->getResult());
    }

    // ------------------------------------------------------------------ Page_Handler_Bookmark

    public function testPageHandlerBookmark_RendersOneLinkPerActiveBookmark(): void
    {
        $id = $this->createBookmark($this->name());
        $inactive = $this->createBookmark($this->name('-INACTIVE'));
        $this->bookmark->updateBookmarks([(string) $inactive], 0);

        $links = (new Page_Handler_Bookmark($this->bookmark))->getBookmark_list();

        $this->assertContains('<a href="?id=' . $id . '">' . $this->name() . '</a>', $links);
        foreach ($links as $link) {
            $this->assertStringNotContainsString($this->name('-INACTIVE'), $link, 'inactive bookmarks are not listed');
        }
    }

    public function testPageHandlerBookmark_NameIsNotEscaped_documentsCurrentBehaviour(): void
    {
        $id = $this->createBookmark($this->name() . '<b>bold</b>');

        $links = (new Page_Handler_Bookmark($this->bookmark))->getBookmark_list();

        $this->assertContains('<a href="?id=' . $id . '">' . $this->name() . '<b>bold</b></a>', $links);
    }

    /**
     * @group known-bug
     * Page_Handler_Bookmark.php:15-19 concatenates the stored name raw into the anchor (stored XSS via
     * process/createBookmark.php). Intended: HTML in a bookmark name is escaped like every other view output.
     */
    public function testPageHandlerBookmark_NameWithHtml_IsEscaped(): void
    {
        $id = $this->createBookmark($this->name() . '<b>bold</b>');

        $links = (new Page_Handler_Bookmark($this->bookmark))->getBookmark_list();

        $this->assertContains('<a href="?id=' . $id . '">' . $this->name() . '&lt;b&gt;bold&lt;/b&gt;</a>', $links);
    }
}
