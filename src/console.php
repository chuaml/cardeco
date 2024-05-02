<?php

namespace console;

class dev
{
    static function dumpjson($objectList): void
    {
        header('Cache-Control: max-age=1, public');
        header('Content-Type: application/json;charset=UTF-8');
        exit(json_encode($objectList));
    }

    static function dump($data): void{
        header('Cache-Control: no-cache');
        header('Content-Type: text/html;charset=UTF-8');
        exit(var_dump($data));
    }
}
