<?php 
namespace Product;

require_once(__DIR__ .'/../HTML/TableDisplayer.php');

use \HTML\TableDisplayer;

class ItemEditor extends TableDisplayer{

    private $records = [];
    private $numFloorPage;

    public function setItems(array $Items, int $numPage):void{
        $this->records = [];
        if(sizeof($Items) === 0){return;}

        $this->numFloorPage = $numPage;

        foreach($Items as $Item){
            $this->records[] = array_map(function($v){
                return \htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
            }, $Item->getAll());
        }

        $this->setEditorCells();
    }

    private function setEditorCells():void{
        $len = sizeof($this->records);
        //recordId as row array index
        $recordId; $r;

        for($i=0;$i<$len;++$i){
            $itemId = $this->records[$i]['itemId'];
            $r = &$this->records[$i];

            $r['description'] = 
            '<input type="text" name="r['.$itemId.'][description]" value="'
            .$r['description'].'" maxlength="255" placeholder="description..." readonly required />';

        }
    }

    protected function getReNamedHeader():array{
        $fieldName = [];

        //$fieldName['itemId'] = 'ID';
        $fieldName ['code'] = 'Item Code';
        $fieldName['description'] = 'Description';
        $fieldName['uom'] = 'UOM';
        $fieldName['group'] = 'Group';
        return $fieldName;
    }

    public function getTable():string{
        $header = $this->getReNamedHeader();
        parent::setHead($header, true);
        parent::setBody($this->records);
        parent::setAttributes('id="ItemEditor" border="1"');
        
        return parent::getTable();
    }

}
