<?php

declare(strict_types=1);

namespace test\Unit\HTML;

use HTML\HtmlObject;
use HTML\IHtml;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of HTML\HtmlObject (src/Legacy/HTML/HtmlObject.php), the base of the
 * HtmlTable/HtmlTableRow/HtmlTableCell builder used by Product\ItemEditor and Report\ShippedItemReport.
 */
final class HtmlObjectTest extends TestCase
{
    public function testImplementsIHtml(): void
    {
        $this->assertInstanceOf(IHtml::class, new HtmlObject('div'));
    }

    public function testToHtmlText_NoChildrenNoAttributes_RendersSelfClosingTag(): void
    {
        $this->assertSame('<br/>', (new HtmlObject('br'))->toHtmlText());
    }

    public function testToHtmlText_DefaultTagName_IsObject(): void
    {
        $this->assertSame('<Object/>', (new HtmlObject())->toHtmlText());
    }

    public function testToHtmlText_SingleAttribute_IsQuotedAndEscaped(): void
    {
        $input = (new HtmlObject('input'))->setAttribute('value', 'a"b<c>&\'d');

        $this->assertSame('<input value="a&quot;b&lt;c&gt;&amp;&#039;d"/>', $input->toHtmlText());
    }

    public function testToHtmlText_AttributeNameIsEscapedToo(): void
    {
        $input = (new HtmlObject('input'))->setAttribute('data-x"y', '1');

        $this->assertSame('<input data-x&quot;y="1"/>', $input->toHtmlText());
    }

    public function testToHtmlText_EmptyAttributeValue_RendersEmptyQuotes(): void
    {
        $table = (new HtmlObject('table'))->setAttribute('cd-editable-sheet', '');

        $this->assertSame('<table cd-editable-sheet=""/>', $table->toHtmlText());
    }

    public function testGetAllAttributesString_documentsCurrentBehaviour_NoSeparatorBetweenAttributes(): void
    {
        $input = (new HtmlObject('input'))
            ->setAttribute('name', 'r[42]')
            ->setAttribute('value', 'v')
            ->setAttribute('maxlength', '255');

        $this->assertSame('name="r[42]"value="v"maxlength="255"', $input->getAllAttributesString());
        $this->assertSame('<input name="r[42]"value="v"maxlength="255"/>', $input->toHtmlText());
    }

    /**
     * @group known-bug
     */
    public function testGetAllAttributesString_SeparatesAttributesWithASpace(): void
    {
        // HtmlObject.php:46-49 concatenates `k="v"` pairs with no separator
        $input = (new HtmlObject('input'))->setAttribute('a', '1')->setAttribute('b', '2');

        $this->assertSame('a="1" b="2"', $input->getAllAttributesString());
    }

    public function testGetAllAttributesString_NoAttributes_IsEmptyString(): void
    {
        $this->assertSame('', (new HtmlObject('p'))->getAllAttributesString());
    }

    public function testSetAttribute_ReturnsSelfForChaining(): void
    {
        $object = new HtmlObject('input');

        $this->assertSame($object, $object->setAttribute('name', 'x'));
    }

    public function testSetAttribute_SameNameTwice_OverwritesValue(): void
    {
        $object = (new HtmlObject('input'))->setAttribute('name', 'first')->setAttribute('name', 'second');

        $this->assertSame('second', $object->getAttribute('name'));
        $this->assertSame(['name' => 'second'], $object->getAllAttributes());
    }

    public function testGetAttribute_ReturnsRawUnescapedValue(): void
    {
        $object = (new HtmlObject('input'))->setAttribute('value', '<raw>');

        $this->assertSame('<raw>', $object->getAttribute('value'));
    }

    public function testGetAttribute_MissingName_documentsCurrentBehaviour_RaisesUndefinedIndexAndReturnsNull(): void
    {
        // HtmlObject.php:35 reads $this->attribute[$name] without isset()
        $object = new HtmlObject('input');
        $raised = [];
        set_error_handler(function (int $errno, string $errstr) use (&$raised) {
            $raised[] = $errno;
            return true;
        });
        try {
            $value = $object->getAttribute('missing');
        } finally {
            restore_error_handler();
        }

        $this->assertNull($value);
        $this->assertCount(1, $raised);
        $this->assertContains($raised[0], [E_NOTICE, E_WARNING]); // E_NOTICE on PHP 7, E_WARNING on PHP 8
    }

    public function testGetAllAttributes_ReturnsInsertionOrderedNameValueMap(): void
    {
        $object = (new HtmlObject('input'))->setAttribute('b', '2')->setAttribute('a', '1');

        $this->assertSame(['b' => '2', 'a' => '1'], $object->getAllAttributes());
    }

    public function testAddChild_ReturnsTheChildAndAppendsItInOrder(): void
    {
        $parent = new HtmlObject('div');
        $first = new HtmlObject('span');
        $second = new HtmlObject('em');

        $this->assertSame($first, $parent->addChild($first));
        $this->assertSame($second, $parent->addChild($second));
        $this->assertSame([$first, $second], $parent->getChildNodes());
    }

    public function testGetChildNodes_Initially_IsEmpty(): void
    {
        $this->assertSame([], (new HtmlObject('div'))->getChildNodes());
    }

    public function testToHtmlText_WithChildren_documentsCurrentBehaviour_OpeningTagIsNeverClosed(): void
    {
        $parent = (new HtmlObject('div'))->setAttribute('id', 'x');
        $parent->addChild(new HtmlObject('span'));

        $this->assertSame('<div id="x"<span/></div>', $parent->toHtmlText());
    }

    /**
     * @group known-bug
     */
    public function testToHtmlText_WithChildren_ClosesTheOpeningTagBeforeChildren(): void
    {
        // HtmlObject.php:57-71: when childNodes is non-empty the '>' of the opening tag is never appended
        $parent = new HtmlObject('div');
        $parent->addChild(new HtmlObject('span'));

        $this->assertSame('<div><span/></div>', $parent->toHtmlText());
    }

    public function testToString_DelegatesToToHtmlText(): void
    {
        $object = (new HtmlObject('hr'))->setAttribute('class', 'thin');

        $this->assertSame($object->toHtmlText(), (string) $object);
        $this->assertSame('<hr class="thin"/>', (string) $object);
    }
}
