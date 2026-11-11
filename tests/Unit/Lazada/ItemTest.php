<?php

declare(strict_types=1);

namespace test\Unit\Lazada;

use Lazada\Item;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the Lazada\Item value object (src/Legacy/Lazada/Item.php).
 * All properties are private and read back through __get() (Item.php:85-87), which
 * Lazada\Manager\ItemManager relies on.
 */
final class ItemTest extends TestCase
{
    public function testConstructor_NoArguments_EverythingIsNull(): void
    {
        $item = new Item();

        $this->assertNull($item->id);
        $this->assertNull($item->lzdSku);
        $this->assertNull($item->sellerSku);
        $this->assertNull($item->name);
        $this->assertNull($item->price);
        $this->assertNull($item->color);
        // the declared default `[]` is overwritten by setImages(null) in the constructor
        $this->assertNull($item->images);
        $this->assertNull($item->weight);
        $this->assertNull($item->length);
        $this->assertNull($item->width);
        $this->assertNull($item->height);
        $this->assertNull($item->status);
    }

    public function testConstructor_PositionalArguments_WidthComesBeforeLength(): void
    {
        // Item.php:18-22 declares (..., $weight, $width, $length, $height) although the setters run length-first
        $item = new Item(7, 'ZZLZD-0001', 'ZZTEST-001', 'Test Seat Cover', 12.9, 'Black', ['a.jpg'], 0.5, 20.0, 30.0, 10.5, 1);

        $this->assertSame(7, $item->id);
        $this->assertSame('ZZLZD-0001', $item->lzdSku);
        $this->assertSame('ZZTEST-001', $item->sellerSku);
        $this->assertSame('Test Seat Cover', $item->name);
        $this->assertSame(12.9, $item->price);
        $this->assertSame('Black', $item->color);
        $this->assertSame(['a.jpg'], $item->images);
        $this->assertSame(0.5, $item->weight);
        $this->assertSame(20.0, $item->width);
        $this->assertSame(30.0, $item->length);
        $this->assertSame(10.5, $item->height);
        $this->assertSame(1, $item->status);
    }

    public function testSetters_OverwriteValues_AndAcceptNull(): void
    {
        $item = new Item(1, 'A', 'B', 'C', 1.0, 'D', ['x'], 1.0, 1.0, 1.0, 1.0, 1);

        $item->setId(null);
        $item->setLzdSku('ZZLZD-0002');
        $item->setSellerSku(null);
        $item->setName('Renamed');
        $item->setPrice(0.0);
        $item->setColor(null);
        $item->setImages([]);
        $item->setWeight(2.25);
        $item->setLength(null);
        $item->setWidth(3.0);
        $item->setHeight(null);
        $item->setStatus(0);

        $this->assertNull($item->id);
        $this->assertSame('ZZLZD-0002', $item->lzdSku);
        $this->assertNull($item->sellerSku);
        $this->assertSame('Renamed', $item->name);
        $this->assertSame(0.0, $item->price);
        $this->assertNull($item->color);
        $this->assertSame([], $item->images);
        $this->assertSame(2.25, $item->weight);
        $this->assertNull($item->length);
        $this->assertSame(3.0, $item->width);
        $this->assertNull($item->height);
        $this->assertSame(0, $item->status);
    }

    public function testMagicGet_UnknownProperty_RaisesPhpError(): void
    {
        // __get() dereferences $this->$property blindly (Item.php:86) -> Notice (7.x) / Warning (8.x)
        $item = new Item();

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Undefined property: Lazada\Item::$doesNotExist');
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $item->doesNotExist;
        } finally {
            restore_error_handler();
        }
    }
}
