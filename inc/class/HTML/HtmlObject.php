<?php

namespace HTML;

class HtmlObject implements IHtml
{
    private $attribute = [];

    public function setAttribute(string $name, ?string $value):void
    {
        $this->attribute[$name] = $value;
    }

    public function getAttribute(string $name)
    {
        return $this->attribute[$name];
    }

    public function getAllAttributes():array
    {
        return $this->attribute;
    }

    final public function getAllAttributesString():string
    {
        $attribute = '';
        foreach ($this->attribute as $k => $v) {
            $attribute .= "{$k}=\"$v\";";
        }
        return rtrim($attribute, ';');
    }

    public function toHtmlText():string
    {
        return '<Object />';
    }

    public function __toString():string
    {
        return $this->toHtmlText();
    }
}
