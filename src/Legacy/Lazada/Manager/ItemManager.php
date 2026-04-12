<?php 
namespace Lazada\Manager;

use Exception;

class ItemManager{
    private $con;

    public function __construct(\mysqli $con){
        $this->con = $con;
    }

    public function selectAll(string $column = '*', int $offset = 0, int $limit = 65535):array{
        $stmt = $this->con->prepare(
            "SELECT {$column} FROM lzd_products LIMIT ?,?"
        );
        $stmt->bind_param('ii', $offset, $limit);
        if(!($stmt->execute())){
            throw new Exception($stmt->error);
        }
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function selectBySellerSku(array $sellerSku):array{
        $len = count($sellerSku);
        if($len === 0){return [];}
        $dataType = implode('', array_fill(0, $len, 's'));
        $param = implode(',', array_fill(0, $len, '?'));
        $stmt = $this->con->prepare(
            "SELECT * FROM lzd_products WHERE seller_sku IN ({$param})"
        );
        $stmt->bind_param($dataType, ...$sellerSku);
        if(!($stmt->execute())){
            throw new Exception($stmt->error);
        }
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function selectById(int $id):array{
        $stmt = $this->con->prepare(
            'SELECT * FROM lzd_products WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        if(!($stmt->execute())){
            throw new Exception($stmt->error);
        }
        return $stmt->fetch_all(MYSQLI_ASSOC);
    }

    public function insert(array $Items):void{
        $stmt = $this->con->prepare(
            'INSERT INTO lzd_products(lzd_sku, seller_sku, name, price, color, image1, image2, image3, image4, image5, weight, length, width, height, status) ' 
            .'VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        $stmt->bind_param('sssdssssssddddi', 
            $lzdSku, $sellerSku, $name, $price, $color, 
            $image1, $image2, $image3, $image4, $image5, 
            $weight, $length, $width, $height, $status
        );

        foreach($Items as $Item){
            $lzdSku = $Item->lzdSku;
            $sellerSku = $Item->sellerSku;
            $name = $Item->name;
            $price = $Item->price;
            $color = $Item->color;
            $image1 = $Item->images[0] ?? '';
            $image2 = $Item->images[1] ?? '';
            $image3 = $Item->images[2] ?? '';
            $image4 = $Item->images[3] ?? '';
            $image5 = $Item->images[4] ?? '';
            $weight = $Item->weight;
            $length = $Item->length;
            $width = $Item->width;
            $height = $Item->height;
            $status = $Item->status;

            if(!($stmt->execute())){
                throw new \Exception($stmt->error);
            }
        }

        $stmt->close();
    }

    public function updateById(array $Items):void{
        $stmt = $this->con->prepare(
            'UPDATE lzd_products SET lzd_sku = ?, seller_sku = ?, name = ?, price = ?, '
            .'color = ?, image1 = ?, image2 = ?, image3 = ?, image4 = ?, image5 = ?, ' 
            .'weight = ?, length = ?, width = ?, height = ?, status = ? ' 
            .'WHERE id = ?'
        );
        $stmt->bind_param('sssdssssssddddii', 
            $lzdSku, $sellerSku, $name, $price, $color, 
            $image1, $image2, $image3, $image4, $image5, 
            $weight, $length, $width, $height, $status,
            $id
        );

        foreach($Items as $Item){
            $id = $Item->id;
            $lzdSku = $Item->lzdSku;
            $sellerSku = $Item->sellerSku;
            $name = $Item->name;
            $price = $Item->price;
            $color = $Item->color;
            $image1 = $Item->images[0] ?? '';
            $image2 = $Item->images[1] ?? '';
            $image3 = $Item->images[2] ?? '';
            $image4 = $Item->images[3] ?? '';
            $image5 = $Item->images[4] ?? '';
            $weight = $Item->weight;
            $length = $Item->length;
            $width = $Item->width;
            $height = $Item->height;
            $status = $Item->status;

            if(!($stmt->execute())){
                throw new \Exception($stmt->error);
            }
        }
        $stmt->close();
    }

    public function updateByLzdSku(array $Items):void{
        $stmt = $this->con->prepare(
            'UPDATE lzd_products SET seller_sku = ?, name = ?, price = ?, '
            .'color = ?, image1 = ?, image2 = ?, image3 = ?, image4 = ?, image5 = ?, ' 
            .'weight = ?, length = ?, width = ?, height = ?, status = ? ' 
            .'WHERE lzd_sku = ?'
        );

        $stmt->bind_param('ssdssssssddddis', 
            $sellerSku, $name, $price, $color, 
            $image1, $image2, $image3, $image4, $image5, 
            $weight, $length, $width, $height, $status,
            $lzdSku
        );

        foreach($Items as $Item){
            $lzdSku = $Item->lzdSku;
            $sellerSku = $Item->sellerSku;
            $name = $Item->name;
            $price = $Item->price;
            $color = $Item->color;
            $image1 = $Item->images[0] ?? '';
            $image2 = $Item->images[1] ?? '';
            $image3 = $Item->images[2] ?? '';
            $image4 = $Item->images[3] ?? '';
            $image5 = $Item->images[4] ?? '';
            $weight = $Item->weight;
            $length = $Item->length;
            $width = $Item->width;
            $height = $Item->height;
            $status = $Item->status;

            if(!($stmt->execute())){
                throw new \Exception($stmt->error);
            }
        }
        $stmt->close();
    }
    
}
