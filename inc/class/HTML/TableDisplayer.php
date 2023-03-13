<?php 
namespace HTML;

class TableDisplayer{
    
    private $attribute = null;
    private $thead = null;
    private $tbody = '';
    private $tfoot = null;
    private $theadKeys = null;

    public function getTable():string{
        $table = '<table';
        if(strlen($this->attribute) > 0){
            $table .= ' ' .$this->attribute;
        }
        $table .= '>';

        if(strlen($this->thead) > 0){
            $table .= $this->thead;
        }

        $table .= $this->tbody;

        if(strlen($this->tfoot) > 0){
            $table .= $this->tfoot;
        }
        $table .= '</table>';
        return $table;
    }

    public function setBody(array &$data):void{
        $this->tbody = '<tbody>';
        if($this->theadKeys === null){
            foreach($data as $row){
                $this->tbody .= '<tr><td>'
                .implode('</td><td>', $row)
                .'</td></tr>';
            }
        }else{
            foreach($data as $row){
                $this->tbody .= '<tr>';
                foreach($this->theadKeys as $index){
                    $value = $row[$index];
                    if(is_float($value) === true){
                        $this->tbody .= '<td>' .number_format($value, 2, '.', ',') .'</td>';
                    } else {
                        $this->tbody .= '<td>' .$value .'</td>';
                    }
                }
                $this->tbody .= '</tr>';
            }
        }
        $this->tbody .= '</tbody>';
    }

    public function setHead(?array $data, bool $setId = true):void{
        if($data === null){
            $this->thead = null;
            $this->theadKeys = null;
            return;
        }
        $this->thead = '<thead><tr>';
        if($setId){
            foreach($data as $k => $v){
                $this->thead .= "<th id=\"{$k}\">{$v}</th>";
            }
        } else {
            foreach($data as $v){
                $this->thead .= "<th>{$v}</th>";
            }
        }
        $this->thead .= '</tr></thead>';
        $this->theadKeys = array_keys($data);
    }

    public function setFoot(?array $data):void{
        if($data === null){
            $this->tfoot = null;
            return;
        }
        $this->tfoot = '<tfoot><tr>'; 
        foreach($data as $v){
            $this->tfoot .= "<td>{$v}</td>";
        }
        $this->tfoot .= '</tr></tfoot>';
    }
    
    public function setAttributes(?string $htmlAttributes):void{
        $this->attribute = $htmlAttributes;
    }

    protected function getReNamedHeader():array{
        //key original fieldName, value as new user friendly field name
        return [];
    }
}
