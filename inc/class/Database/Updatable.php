<?php 
namespace Database;

interface Updatable{
    public function update(\mysqli $con, array &$Data):void;
}
