<?php

declare(strict_types=1);

namespace test\Integration;

use catalog_generator;
use test\Support\DatabaseTestCase;

/**
 * Characterization of catalog_generator (src/Legacy/catalog_generator.php), the printable catalog behind
 * process/printCatalog.php: the posted rows (`row[i][id]`, `row[i][comment]`) -> stock items -> A4 pages.
 *
 * An item without images is the interesting case for the PHP 7.3 / 8.3 rule: its `image` column is NULL
 * (GROUP_CONCAT over the LEFT JOIN to stock_images) and used to reach trim(), which is deprecated since PHP 8.1.
 */
final class CatalogGeneratorTest extends DatabaseTestCase
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

    public function testGenerateCatalog_ItemWithoutImages_EncodesItsNullImageAsAnEmptyString(): void
    {
        $id = $this->insertStockItem($this->code('A'), 'ALPHA <b>&</b>', 3);
        $catalog = new catalog_generator();

        $catalog->generateCatalog($this->con, [['id' => (string) $id, 'comment' => ' note <i> ']]);

        $this->assertSame([
            [
                'item_code' => $this->code('A'),
                'description' => 'ALPHA &lt;b&gt;&amp;&lt;/b&gt;',
                'image' => '',
                'comment' => 'note &lt;i&gt;',
            ],
        ], $catalog->items);
    }

    public function testDrawCatalog_ItemWithoutImages_RendersCodeAndDescriptionWithoutARemarkRow(): void
    {
        $id = $this->insertStockItem($this->code('A'), 'ALPHA', 3);
        $catalog = new catalog_generator();
        $catalog->generateCatalog($this->con, [['id' => (string) $id, 'comment' => '']]);

        $html = $catalog->drawCatalog(1, false);

        $this->assertStringContainsString('<div class="a4_page">', $html);
        $this->assertStringContainsString('<tr><th>Item Code: </th><td>' . $this->code('A') . '</td></tr>', $html);
        $this->assertStringContainsString('<tr><th>Description: </th><td>ALPHA</td></tr>', $html);
        $this->assertStringNotContainsString('Remark', $html);
    }

    public function testGenerateCatalog_RowWithoutId_IsSkipped(): void
    {
        // an unchecked row of the form posts its comment but no id
        $catalog = new catalog_generator();

        $catalog->generateCatalog($this->con, [['comment' => 'zz not selected']]);

        $this->assertSame([], $catalog->items);
    }

    public function testGenerateCatalog_NoneOfThePostedIdsExists_LeavesTheItemListEmpty(): void
    {
        // stock_items.id is AUTO_INCREMENT, so 0 never exists; encodeResult() used to read $this->items[0] unguarded
        $catalog = new catalog_generator();

        $catalog->generateCatalog($this->con, [['id' => '0', 'comment' => '']]);

        $this->assertSame([], $catalog->items);
    }
}
