<?php

declare(strict_types=1);

namespace test\Integration\Handler;

use App\Handler\BigsellerHandler;
use App\Handler\IndexHandler;
use App\Handler\LazadaHandler;
use App\Handler\Response;
use App\Handler\ShopeeHandler;
use App\Handler\TiktokHandler;
use test\Mock;
use test\Support\DatabaseTestCase;

/**
 * The four daily-order pages share AbstractDailyOrderHandler; every platform must expose the same view
 * contract (Data / msg / jsonOrders / dailyOrderFile_Sha1Hash) and never leak an exception.
 */
final class DailyOrderHandlerTest extends DatabaseTestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> handler class, file field, view, sample file */
    public function handlerProvider(): array
    {
        return [
            'lazada' => [LazadaHandler::class, 'lzdOrders', 'view/lazada.php', 'tests/lazada/data.input/lazada.input.order.sample.xlsx'],
            'index' => [IndexHandler::class, 'lzdOrders', 'view/lazada.php', 'tests/lazada/data.input/lazada.input.order.sample.xlsx'],
            'bigseller' => [BigsellerHandler::class, 'dailyOrders', 'view/bigseller.html', 'tests/bigseller/data.input/bigseller.input.order.sample.2025-04-02.xlsx'],
            'tiktok' => [TiktokHandler::class, 'orderFile', 'view/tiktok.html', 'tests/tiktok/data.input/tiktok.input.order.sample.csv'],
            'shopee' => [ShopeeHandler::class, 'shopeeOrders', 'view/shopee.html', 'tests/shopee/data.input/shopee.input.order.sample.csv'],
        ];
    }

    /**
     * @dataProvider handlerProvider
     */
    public function testHandle_NoUpload_ReturnsEmptyViewData(string $class, string $field, string $view): void
    {
        $response = (new $class($this->con))->handle([], []);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($view, $response->viewPath);
        $this->assertSame(
            [
                'Data' => ['toRestock' => '', 'toCollect' => '', 'notFound' => '', 'orders' => ''],
                'msg' => '',
                'jsonOrders' => '',
                'dailyOrderFile_Sha1Hash' => '',
            ],
            $response->data
        );
    }

    /**
     * @dataProvider handlerProvider
     */
    public function testHandle_SampleUpload_PopulatesTablesJsonAndHash(string $class, string $field, string $view, string $sample): void
    {
        $response = (new $class($this->con))->handle([], Mock::newFile($field, $sample));

        $data = $response->data;
        $this->assertSame('', $data['msg'], 'no error for the sample file');
        foreach (['toRestock', 'toCollect', 'notFound', 'orders'] as $key) {
            $this->assertStringStartsWith('<table', $data['Data'][$key], $key);
        }
        $orders = json_decode($data['jsonOrders'], true);
        $this->assertIsArray($orders);
        $this->assertNotEmpty($orders);
        $this->assertArrayHasKey('orderNum', $orders[0]);
        $this->assertArrayHasKey('sku', $orders[0]);
        $this->assertSame(sha1_file($sample), $data['dailyOrderFile_Sha1Hash']);
    }

    /**
     * @dataProvider handlerProvider
     */
    public function testHandle_UnreadableUpload_ReportsMessageInsteadOfThrowing(string $class, string $field, string $view): void
    {
        // a file of the wrong format for every platform
        $wrongFile = 'tests/bigseller/data.input/bigseller.output.order.expected.json';

        $response = (new $class($this->con))->handle([], Mock::newFile($field, $wrongFile));

        $this->assertNotSame('', $response->data['msg'], 'parse failure is shown to the user');
        $this->assertSame('', $response->data['Data']['orders']);
        $this->assertSame($view, $response->viewPath);
    }

    /**
     * @dataProvider handlerProvider
     */
    public function testHandle_UploadError_ReportsFileName(string $class, string $field, string $view, string $sample): void
    {
        $files = Mock::newFile($field, $sample, '', 'orders.xlsx', UPLOAD_ERR_PARTIAL);

        $response = (new $class($this->con))->handle([], $files);

        $this->assertSame('error on file: orders.xlsx', $response->data['msg']);
        $this->assertSame('', $response->data['jsonOrders']);
    }

    /**
     * @dataProvider handlerProvider
     */
    public function testHandle_OtherFileField_IsIgnored(string $class, string $field, string $view, string $sample): void
    {
        $response = (new $class($this->con))->handle([], Mock::newFile('someOtherField', $sample));

        $this->assertSame('', $response->data['jsonOrders']);
        $this->assertSame('', $response->data['msg']);
    }
}
