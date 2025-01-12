<?php

namespace HTML;

class HtmlObject implements IHtml
{
    private $attribute = [];

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
        return '<Object />';
    }

    public function __toString(): string
    {
        return $this->toHtmlText();
    }
}
