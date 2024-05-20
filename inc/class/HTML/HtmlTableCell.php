<?php

namespace HTML;

class HtmlTableCell extends HtmlObject
{
    private $Value = '';

    public function __construct($Object = '')
    {
        $this->setValue($Object);
    }

    public function setValue($Object): void
    {
        $this->Value = $Object;
    }

    public function getValue()
    {
        return $this->Value;
    }

    public function getFormattedValue(): string
    {
        if ($this->Value === null) {
            return '<i data-type="null"></i>';
        } elseif (\is_object($this->Value) === true) {
            return (string) $this->Value;
        }

        return \htmlspecialchars(trim((string) $this->Value), \ENT_QUOTES, 'UTF-8');
    }

    public function toHtmlText(): string
    {
        $attr = parent::getAllAttributesString();
        if ($attr === '') {
            return '<td>' . $this->getFormattedValue() . '</td>';
        }

        return "<td {$attr}>" . $this->getFormattedValue() . '</td>';
    }
}
