<?php

declare(strict_types=1);

namespace Sumish\Tests;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use Sumish\Request;

class RequestTest extends TestCase
{
    private array $server = [];
    private array $get = [];
    private array $post = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->get = $_GET;
        $this->post = $_POST;

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test/uri'
        ];
        $_GET = ['getKey' => '<script>alert(1)</script>'];
        $_POST = ['postKey' => '<b>test</b>'];
    }

    // __construct(): инициализация GET и POST данных
    public function testGetAndPostInitialization(): void
    {
        $request = new Request();

        $this->assertSame(['getKey' => '&lt;script&gt;alert(1)&lt;/script&gt;'], $request->get);
        $this->assertSame(['postKey' => '&lt;b&gt;test&lt;/b&gt;'], $request->post);
    }

    // __construct(): инициализация COOKIE данных
    public function testCookieInitialization(): void
    {
        $_COOKIE = ['cookieKey' => '<b>cookieValue</b>'];
        $request = new Request();

        $this->assertSame(['cookieKey' => '&lt;b&gt;cookieValue&lt;/b&gt;'], $request->cookie);
    }

    // __construct(): инициализация FILES данных
    public function testFilesInitialization(): void
    {
        $_FILES = [
            'file' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/php12345',
                'error' => 0,
                'size' => 123,
            ],
        ];

        $request = new Request();

        $this->assertEquals($_FILES, $request->files);
    }

    // __get(): доступ к свойствам через магический метод
    public function testMagicGetAccess(): void
    {
        $request = new Request();

        $this->assertNull($request->nonExistentProperty);
    }

    // getUri(): получение базового URI
    public function testGetUri(): void
    {
        $request = new Request();

        $this->assertSame('/test/uri', $request->getUri());
    }

    // getUri(): обработка URI с query string
    public function testGetUriWithQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/test/uri?param=value';
        $request = new Request();

        $this->assertSame('/test/uri', $request->getUri());
    }

    // getMethod(): получение HTTP метода
    public function testGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();

        $this->assertSame('POST', $request->getMethod());
    }

    // getMethod(): обработка некорректного метода
    public function testInvalidMethodFallbackToGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'INVALID';
        $request = new Request();

        $this->assertSame('GET', $request->getMethod());
    }

    // get() и post(): проверка HTTP методов
    public function testGetAndPostMethods(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();

        $this->assertTrue($request->get());
        $this->assertFalse($request->post());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();

        $this->assertFalse($request->get());
        $this->assertTrue($request->post());
    }

    // clean(): очистка простых данных
    public function testCleanMethod(): void
    {
        $request = new Request();

        $reflection = new ReflectionClass($request);
        $cleanMethod = $reflection->getMethod('clean');
        $cleanMethod->setAccessible(true);

        $input = '<script>alert("test")</script>';
        $expected = '&lt;script&gt;alert(&quot;test&quot;)&lt;/script&gt;';
        $this->assertSame($expected, $cleanMethod->invoke($request, $input));

        $inputArray = ['key' => '<b>bold</b>', 'nested' => ['<i>italic</i>']];
        $expectedArray = ['key' => '&lt;b&gt;bold&lt;/b&gt;', 'nested' => ['&lt;i&gt;italic&lt;/i&gt;']];
        $this->assertSame($expectedArray, $cleanMethod->invoke($request, $inputArray));
    }

    // clean(): очистка вложенных данных
    public function testCleanNestedData(): void
    {
        $_GET = [
            'nested' => [
                'key1' => '<b>bold</b>',
                'key2' => ['<i>italic</i>'],
            ],
        ];

        $request = new Request();
        
        $this->assertSame(
            [
                'nested' => [
                    'key1' => '&lt;b&gt;bold&lt;/b&gt;',
                    'key2' => ['&lt;i&gt;italic&lt;/i&gt;'],
                ],
            ],
            $request->get
        );
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
        $_POST = $this->post;
    }
}
