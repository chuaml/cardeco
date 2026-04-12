<?php

namespace App\Handler;

use mysqli;
use Exception;
use OrderProcess\LazadaOrderProcess;

class LazadaHandler
{
    private $con;

    public function __construct(mysqli $con)
    {
        $this->con = $con;
    }

    public function handle(array $request, array $files): Response
    {
        $data = [
            'Data' => [
                'toRestock' => '',
                'toCollect' => '',
                'notFound' => '',
                'orders' => ''
            ],
            'msg' => '',
            'jsonOrders' => '',
            'dailyOrderFile_Sha1Hash' => ''
        ];

        try {
            if (isset($files['lzdOrders'])) {
                try {
                    $L = new LazadaOrderProcess($this->con, $files['lzdOrders']['tmp_name']);
                    $data['Data'] = $L->getData();
                    $data['jsonOrders'] = json_encode($L->getOrders());
                    $data['dailyOrderFile_Sha1Hash'] = sha1_file($files['lzdOrders']['tmp_name']);
                } catch (Exception $e) {
                    $data['msg'] = $e->getMessage();
                }
            }
        } finally {
            // $this->con->close(); // avoid closing here if other handlers or view might need it, but the original did it.
        }

        return new Response('view/lazada.php', $data);
    }
}
