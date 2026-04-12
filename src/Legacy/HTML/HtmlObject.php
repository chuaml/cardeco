<?php

namespace HTML;

class HtmlObject implements IHtml
{
    private $attribute = [];
    private $tagName = 'Object';
    private $childNodes = [];

    public function __construct(string $tagName = 'Object')
    {
        $this->tagName = $tagName;
    }

    public function addChild(HtmlObject $HtmlObject): HtmlObject
    {
        $this->childNodes[] = $HtmlObject;
        return $HtmlObject;
    }

    public function getChildNodes(): array
    {
        return $this->childNodes;
    }

    public function setAttribute(string $name, ?string $value): HtmlObject
    {
        $this->attribute[$name] = $value;
        return $this;
    }

    public function getAttribute(string $name)
    {
        return $this->attribute[$name];
    }

    public function getAllAttributes(): array
    {
        return $this->attribute;
    }

    final public function getAllAttributesString(): string
    {
        $attribute = '';
        foreach ($this->attribute as $k => $v) {
            $attribute .=
                htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $attribute;
    }

    public function toHtmlText(): string
    {
        $output = '';
        $attr = $this->getAllAttributesString();
        if ($attr === '') {
            $output = "<$this->tagName";
        } else {
            $output = "<$this->tagName " . $attr ;
        }

        $count = count($this->childNodes);
        if ($count === 0) {
            $output .= '/>';
        } else {
            for ($i = 0; $i < $count; $i++) {
                $output .= $this->childNodes[$i]->toHtmlText();
            }
            $output .= "</$this->tagName>";
        }

        return $output;
    }

    public function __toString(): string
    {
        return $this->toHtmlText();
    }
}
