<?php

declare(strict_types=1);

namespace Sumish\Tests;

use ReflectionClass;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sumish\Response;

class ResponseTest extends TestCase
{
    private Response $response;

    protected function setUp(): void
    {
        $this->response = new Response();
    }

    // setStatusCode(): установка различных HTTP статусов
    public function testSetStatusCode(): void
    {
        $this->response->setStatusCode(200);
        $this->assertEquals(200, http_response_code());

        $this->response->setStatusCode(404);
        $this->assertEquals(404, http_response_code());

        $this->response->setStatusCode(500);
        $this->assertEquals(500, http_response_code());
    }

    // addHeader(): добавление одиночного заголовка
    public function testAddHeader(): void
    {
        $this->response->addHeader('X-Test-Header: value');
        $headers = $this->response->getHeaders();

        $this->assertCount(1, $headers);
        $this->assertEquals('X-Test-Header: value', $headers[0]);
    }

    // addHeaders(): добавление нескольких заголовков
    public function testAddHeaders(): void
    {
        $this->response->addHeaders(['X-Test-Header1: value1', 'X-Test-Header2: value2']);
        $headers = $this->response->getHeaders();

        $this->assertCount(2, $headers);
        $this->assertEquals('X-Test-Header1: value1', $headers[0]);
        $this->assertEquals('X-Test-Header2: value2', $headers[1]);
    }

    // setOutput(): установка выходных данных
    public function testSetOutput(): void
    {
        $this->response->setOutput('Test data');
        $this->assertEquals('Test data', $this->response->getOutput());
    }

    // setOutput(): обработка null значения
    public function testSetOutputNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Output cannot be null.");

        $this->response->setOutput(null);
    }

    // addOutput(): добавление данных к существующему выводу
    public function testAddOutput(): void
    {
        $this->response->setOutput('Test');
        $this->response->addOutput(' data');
        $this->assertEquals('Test data', $this->response->getOutput());
    }

    // setCompression(): установка уровня сжатия
    public function testSetCompression(): void
    {
        $this->response->setCompression(5);

        $reflection = new ReflectionClass($this->response);
        $property = $reflection->getProperty('level');
        $property->setAccessible(true);

        $this->assertEquals(5, $property->getValue($this->response));
    }

    // compressOutput(): обработка некорректного уровня сжатия
    public function testCompressionLevelOutOfRange(): void
    {
        $this->response->setCompression(10);
        $this->response->setOutput('Test data');
    
        $compressedData = $this->response->compressOutput();
        $this->assertEquals('Test data', $compressedData);
    }
    
    // compressOutput(): обработка отрицательного уровня сжатия
    public function testCompressionLevelNegative(): void
    {
        $this->response->setCompression(-2);
        $this->response->setOutput('Test data');
    
        $compressedData = $this->response->compressOutput();
        $this->assertEquals('Test data', $compressedData);
    }

    // compressOutput(): базовое сжатие данных
    public function testCompressOutput(): void
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate';

        $this->response->setCompression(6);
        $this->response->setOutput('Test data for compression');

        $compressedData = $this->response->compressOutput();

        $this->assertNotEquals('Test data for compression', $compressedData);
        $this->assertStringStartsWith("\x1f\x8b", $compressedData);

        $decompressed = gzdecode($compressedData);
        $this->assertEquals('Test data for compression', $decompressed);

        $headers = $this->response->getHeaders();
        $this->assertContains('Content-Encoding: gzip', $headers);
    }

    // compressOutput(): обработка отсутствия поддержки сжатия
    public function testCompressOutputWithoutAcceptEncoding(): void
    {
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);

        $this->response->setCompression(9);
        $this->response->setOutput('Test data');

        $compressedData = $this->response->compressOutput();
        $this->assertEquals('Test data', $compressedData);
    }

    // compressOutput(): пропуск сжатия
    public function testCompressOutputWithSkipZlib(): void
    {
        $this->response->setCompression(6);
        $this->response->setOutput('Test data');
    
        $compressedData = $this->response->compressOutput(null, true);
        $this->assertEquals('Test data', $compressedData);
    }

    // detectCompressionType(): определение типа сжатия x-gzip
    public function testDetectCompressionTypeXGzip(): void
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'x-gzip, deflate';
        $this->response->setCompression(6);

        $reflection = new ReflectionClass($this->response);
        $method = $reflection->getMethod('detectCompressionType');
        $method->setAccessible(true);
        $compressionType = $method->invoke($this->response);

        $this->assertEquals('x-gzip', $compressionType);
    }

    // send(): отправка сжатых данных
    public function testSendWitCompression(): void
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate';

        $this->response->setCompression(9);
        $this->response->setOutput('Test data');

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $this->assertStringStartsWith("\x1f\x8b", $output);
    }

    // redirect(): перенаправление на другой URL
    public function testRedirect(): void
    {
        $this->response->redirect('https://example.com', 302);

        $this->assertContains('Location: https://example.com', $this->response->getHeaders());
        $this->assertEquals(302, http_response_code());
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }
}
