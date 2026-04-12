<?php 
namespace Lazada;

class Item{
    private $id;
    private $lzdSku;
    private $sellerSku;
    private $name;
    private $price;
    private $color;
    private $images = [];
    private $weight;
    private $length;
    private $width;
    private $height;
    private $status;

    public function __construct(
        ?int $id = null, ?string $lzdSku = null, ?string $sellerSku = null, ?string $name = null, 
        ?float $price = null, ?string $color = null, ?array $images = null, ?float $weight = null, 
        ?float $width = null, ?float $length = null, ?float $height = null, ?int $status = null
    ){
        $this->setId($id);
        $this->setLzdSku($lzdSku);
        $this->setSellerSku($sellerSku);
        $this->setName($name);
        $this->setPrice($price);
        $this->setColor($color);
        $this->setImages($images);
        $this->setWeight($weight);
        $this->setLength($length);
        $this->setWidth($width);
        $this->setHeight($height);
        $this->setStatus($status);
    }

    public function setId(?int $id):void{
        $this->id = $id;
    }

    public function setLzdSku(?string $lzdSku):void{
        $this->lzdSku = $lzdSku;
    }

    public function setSellerSku(?string $sellerSku):void{
        $this->sellerSku = $sellerSku;
    }

    public function setName(?string $name):void{
        $this->name = $name;
    }

    public function setPrice(?float $price):void{
        $this->price = $price;
    }

    public function setColor(?string $color):void{
        $this->color = $color;
    }

    public function setImages(?array $images):void{
        $this->images = $images;
    }

    public function setWeight(?float $weight):void{
        $this->weight = $weight;
    }

    public function setLength(?float $length):void{
        $this->length = $length;
    }

    public function setWidth(?float $width):void{
        $this->width = $width;
    }

    public function setHeight(?float $height):void{
        $this->height = $height;
    }

    public function setStatus(?int $status):void{
        $this->status = $status;
    }
    
    public function __get(string $property){
        return $this->$property;
    }

}
