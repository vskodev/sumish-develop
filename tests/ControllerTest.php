<?php

declare(strict_types=1);

namespace Sumish\Tests;

use ArrayObject;
use Psr\Container\NotFoundExceptionInterface;
use PHPUnit\Framework\TestCase;
use Sumish\Container;
use Sumish\Controller;

class ControllerTest extends TestCase
{
    private Container $container;
    private Controller $controller;

    protected function setUp(): void
    {
        $this->container = Container::create([]);
        $this->container->set('container', $this->container);
        $this->controller = new TestController($this->container);
    }

    // __construct: базовая функциональность
    public function testControllerConstruction(): void
    {
        $this->assertInstanceOf(Controller::class, $this->controller);
        $this->assertSame($this->container, $this->controller->container());
    }

    // __get: получение простого компонента
    public function testSimpleComponentRetrieval(): void
    {
        $this->container->set('config', new ArrayObject(['compression' => 9]));
        
        $this->assertEquals(
            ['compression' => 9], 
            $this->controller->config->getArrayCopy()
        );
    }

    // __get: получение вызываемого компонента
    public function testCallableComponentRetrieval(): void
    {
        $this->container->set('logger', new TestLogger());
        
        $this->assertInstanceOf(TestLogger::class, $this->controller->logger);
        $this->assertEquals('test', $this->controller->logger->log('test'));
    }

    // __get: исключение при отсутствии компонента
    public function testNonExistentComponentRetrieval(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage("Component 'nonexistent' not found.");
        
        $this->controller->nonexistent;
    }

    // container(): базовая функциональность
    public function testContainerRetrieval(): void
    {
        $this->assertSame($this->container, $this->controller->container());
    }

    // match: инициализация свойства
    public function testMatchPropertyInitialization(): void
    {   
        $this->assertIsArray($this->controller->match);
        $this->assertEmpty($this->controller->match);
    }

    // match: установка значения свойства
    public function testMatchPropertyAssignment(): void
    {
        $match = [
            'controller' => 'TestController',
            'action' => 'index',
            'parameters' => ['id' => '1']
        ];
        
        $this->controller->match = $match;
        $this->assertEquals($match, $this->controller->match);
    }
}

// Для создания экземпляра контроллера
class TestController extends Controller
{
    public function index(): void
    {
    }
}

// Для тестирования получения компонентов
class TestLogger
{
    public function log(string $message): string
    {
        return $message;
    }
}
