<?php

declare(strict_types=1);

namespace test\Unit\Handler;

use App\Handler\Response;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of App\Handler\Response (src/Handler/Response.php): the value returned by Tier-1
 * handlers; router.php does `$_view_html_path = $response->viewPath; extract($response->data);`.
 */
final class ResponseTest extends TestCase
{
    public function testDefaults_EmptyViewPathAndEmptyData(): void
    {
        $response = new Response();

        $this->assertSame('', $response->viewPath);
        $this->assertSame([], $response->data);
    }

    public function testConstructor_StoresViewPathAndData(): void
    {
        $data = ['msg' => 'ok', 'Data' => ['toCollect' => []], 'jsonOrders' => '[]'];

        $response = new Response('view/lazada.php', $data);

        $this->assertSame('view/lazada.php', $response->viewPath);
        $this->assertSame($data, $response->data);
    }

    public function testEmptyViewPath_MeansNoLayoutIsRendered(): void
    {
        // router.php only requires view/_layout.main.html when viewPath !== ''
        $response = new Response('', ['x' => 1]);

        $this->assertSame('', $response->viewPath);
    }

    public function testPropertiesArePublicAndMutable(): void
    {
        $response = new Response('view/a.php', ['a' => 1]);

        $response->viewPath = 'view/b.php';
        $response->data['b'] = 2;

        $this->assertSame('view/b.php', $response->viewPath);
        $this->assertSame(['a' => 1, 'b' => 2], $response->data);
    }

    public function testDataKeys_AreExtractedAsViewVariables(): void
    {
        // mirrors router.php:36 - string keys become locals in the view scope
        $response = new Response('view/x.php', ['msg' => 'hello', 'count' => 3]);

        extract($response->data);

        $this->assertSame('hello', $msg);
        $this->assertSame(3, $count);
    }
}
