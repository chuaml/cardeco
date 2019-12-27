<?php 
namespace Database;

interface Insertable{
    public function insert(\mysqli $con, array &$Data):void;
}
