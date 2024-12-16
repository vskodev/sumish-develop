<?php

declare(strict_types=1);

namespace Sumish\Tests;

use PHPUnit\Framework\TestCase;
use Sumish\Application;
use Sumish\Container;
use Sumish\Controller;
use Sumish\Request;
use Sumish\Response;
use Sumish\Router;
use Sumish\Exception\HandlerException;

class ApplicationTest extends TestCase
{
    private Application $app;
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'routes' => ['/test' => ['controller' => 'TestController', 'action' => 'index']],
            'compression' => 0,
            'headers' => ['X-Test: true']
        ];
        $this->app = new Application($this->config);
    }

    // __construct(): инициализация с пользовательской конфигурацией
    public function testConstructWithConfig(): void
    {
        $config = $this->app->config();
        $this->assertEquals('TestController', $config['routes']['/test']['controller']);
        $this->assertEquals(0, $config['compression']);
        $this->assertArrayHasKey('headers', $config);
    }

    // configure(): слияние с конфигурацией по умолчанию
    public function testConfigureMergesWithDefaults(): void
    {
        $result = $this->app->configure(['compression' => 5]);
        $this->assertEquals(5, $result['compression']);
        $this->assertArrayHasKey('routes', $result);
    }

    // configure(): переопределение значений по умолчанию
    public function testConfigureOverridesDefaults(): void
    {
        $originalRoutes = $this->app->config()['routes'];
        $customRoute = ['/' => ['controller' => 'CustomController', 'action' => 'custom']];
        
        $result = $this->app->configure(['routes' => $customRoute]);
        
        $this->assertNotEquals($originalRoutes, $result['routes']);
        $this->assertArrayHasKey('/', $result['routes']);
        $this->assertEquals('CustomController', $result['routes']['/']['controller']);
        $this->assertEquals('custom', $result['routes']['/']['action']);
    }

    // run(): выполнение полного цикла обработки запроса
    public function testRun(): void
    {
        $container = $this->createMock(Container::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $router = $this->createMock(Router::class);
        $controller = $this->createMock(Controller::class);
    
        // Получаем актуальные данные из конфигурации после слияния
        $actualConfig = $this->app->config();
        $actualRoutes = $actualConfig['routes'];
        $actualHeaders = $actualConfig['headers'];
    
        // Настройка Request
        $request->expects($this->once())
                ->method('getUri')
                ->willReturn('/test');
    
        // Настройка Router с актуальными маршрутами
        $router->expects($this->once())
               ->method('push')
               ->with($actualRoutes)
               ->willReturnSelf();
    
        $router->expects($this->once())
               ->method('match')
               ->with('/test')
               ->willReturn(['controller' => 'TestController', 'action' => 'index']);
    
        $router->expects($this->once())
               ->method('resolveController')
               ->willReturn($controller);
    
        $router->expects($this->once())
               ->method('dispatch')
               ->with($controller)
               ->willReturn('Test Output');
    
        // Настройка Response с актуальными заголовками
        $response->expects($this->once())
                 ->method('addHeaders')
                 ->with($actualHeaders);
    
        $response->expects($this->once())
                 ->method('setCompression')
                 ->with($this->config['compression']);
    
        $response->expects($this->once())
                 ->method('setOutput')
                 ->with('Test Output');
    
        $response->expects($this->once())
                 ->method('send');
    
        // Настройка Container
        $container->expects($this->exactly(3))
                  ->method('get')
                  ->willReturnMap([
                      ['request', $request],
                      ['response', $response],
                      ['router', $router]
                  ]);
    
        // Внедряем container через рефлексию
        $reflection = new \ReflectionClass($this->app);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $property->setValue($this->app, $container);
    
        $this->app->run();
    }

    // run(): перехват и обработка исключений
    public function testRunHandlesExceptionInDispatch(): void
    {
        $container = $this->createMock(Container::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $router = $this->createMock(Router::class);
        $controller = $this->createMock(Controller::class);
        $exception = new \RuntimeException('Dispatch exception');
    
        // Настройка контейнера
        $container->method('get')
            ->willReturnMap([
                ['request', $request],
                ['response', $response],
                ['router', $router],
            ]);
    
        // Настройка Request
        $request->method('getUri')
            ->willReturn('/test');
    
        // Настройка Router
        $router->method('push')
            ->willReturnSelf();
    
        $router->method('match')
            ->willReturn(['controller' => 'TestController', 'action' => 'index']);
    
        $router->method('resolveController')
            ->willReturn($controller);
    
        // Выброс исключения в dispatch
        $router->method('dispatch')
            ->willThrowException($exception);
    
        // Настройка рефлексии для внедрения контейнера
        $reflection = new \ReflectionClass($this->app);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $property->setValue($this->app, $container);
    
        // Перехват вывода для проверки
        ob_start();
        $this->app->run();
        $output = ob_get_clean();
    
        // Проверка, что исключение обработано HandlerException
        $this->assertStringContainsString('Dispatch exception', $output);
    }

    // componentsDefault(): проверка компонентов по умолчанию
    public function testComponentsDefault(): void
    {
        $components = Application::componentsDefault();
        
        $this->assertArrayHasKey('request', $components);
        $this->assertArrayHasKey('response', $components);
        $this->assertArrayHasKey('router', $components);
        $this->assertEquals(Request::class, $components['request']);
    }

    // configDefault(): проверка конфигурации по умолчанию
    public function testConfigDefault(): void
    {
        $config = Application::configDefault();
        
        $this->assertArrayHasKey('routes', $config);
        $this->assertArrayHasKey('headers', $config);
        $this->assertArrayHasKey('components', $config);
        $this->assertArrayHasKey('db', $config);
        $this->assertIsArray($config['headers']);
    }
}
