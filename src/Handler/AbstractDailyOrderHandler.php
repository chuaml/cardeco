<?php

namespace App\Handler;

use mysqli;
use RuntimeException;
use Throwable;

/**
 * Shared controller for the four daily-order pages (Lazada / BigSeller / TikTok / Shopee).
 *
 * The page shows an upload form; when a file arrives under fileField() the platform's order processor
 * turns it into the four pick-list tables. Any failure is reported through `$msg` in the view instead
 * of an error page. The view receives: Data (4 tables), msg, jsonOrders, dailyOrderFile_Sha1Hash.
 */
abstract class AbstractDailyOrderHandler
{
    /** @var mysqli */
    protected $con;

    public function __construct(mysqli $con)
    {
        $this->con = $con;
    }

    /**
     * Name of the <input type="file"> field the page posts, e.g. 'lzdOrders'.
     */
    abstract protected function fileField(): string;

    /**
     * View rendered inside view/_layout.main.html, e.g. 'view/lazada.php'.
     */
    abstract protected function viewPath(): string;

    /**
     * Platform-specific order processor for the uploaded file.
     *
     * @return object exposing getData(): array and getOrders(): array
     */
    abstract protected function newOrderProcess(string $filePath);

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

        $field = $this->fileField();
        if (isset($files[$field])) {
            try {
                $file = $files[$field];
                if (isset($file['error']) && (int) $file['error'] !== 0) {
                    throw new RuntimeException('error on file: ' . (isset($file['name']) ? $file['name'] : ''));
                }
                $process = $this->newOrderProcess($file['tmp_name']);
                $data['Data'] = $process->getData();
                $data['jsonOrders'] = json_encode($process->getOrders());
                $data['dailyOrderFile_Sha1Hash'] = sha1_file($file['tmp_name']);
            } catch (Throwable $e) {
                $data['msg'] = $e->getMessage();
            }
        }

        return new Response($this->viewPath(), $data);
    }
}
