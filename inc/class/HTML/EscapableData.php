<?php 
namespace HTML;

interface EscapableData{
    public function getEscapedData(string $property):string;
}
