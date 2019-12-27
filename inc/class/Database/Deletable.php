<?php 
namespace Database;

interface Deletable{
    public function delete(\mysqli $con, array &$Data):void;
}
