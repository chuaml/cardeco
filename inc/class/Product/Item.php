<?php
namespace Product;

require_once(__DIR__ .'/../HTML/EscapableData.php');

use \HTML\EscapableData;

class Item implements EscapableData
{
    public $itemId;
    public $code;
    public $description;
    public $uom;
    public $group;
    
    public function __construct(
        ?int $itemId,
        ?string $code,
        ?string $description,
        ?string $uom = null,
        ?string $group = null
    ) {
        $this->itemId = $itemId;
        $this->code = $code;
        $this->uom = $uom;
        $this->group = $group;
        $this->description = $description;
    }

    public function __get(string $property)
    {
        return $this->$property;
    }

    public function getEscapedData(string $property):string
    {
        return \htmlspecialchars($this->$property, ENT_QUOTES, 'UTF-8');
    }

    public function getAll():array
    {
        return [
            'itemId' => $this->itemId,
            'code' => $this->code,
            'description' => $this->description,
            'uom' => $this->uom,
            'group' => $this->group
        ];
    }
}
