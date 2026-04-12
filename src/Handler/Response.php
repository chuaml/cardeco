<?php

namespace App\Handler;

class Response
{
    public $viewPath = '';
    public $data = [];

    public function __construct(string $viewPath = '', array $data = [])
    {
        $this->viewPath = $viewPath;
        $this->data = $data;
    }
}
