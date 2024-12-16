<?php

declare(strict_types=1);

namespace Sumish\Tests;

use RuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sumish\Router;
use Sumish\Container;
use Sumish\Controller;
use Sumish\Exception\NotFoundException;

class RouterTest extends TestCase
{
    private Router $router;
    private Container $container;

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->router = new Router($this->container);
    }

    // add(): добавление одного маршрута
    public function testAddSingleRoute(): void
    {
        $uri = '/test';
        $target = ['controller' => 'TestController', 'action' => 'index'];

        $this->router->add($uri, $target);
        $routes = $this->router->get();

        $this->assertArrayHasKey($uri, $routes);
        $this->assertSame($target, $routes[$uri]);
    }

    // add(): обработка пустого URI
    public function testAddThrowsExceptionForInvalidUri(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->router->add('', ['controller' => 'TestController', 'action' => 'index']);
    }

    // add(): обработка неполного target
    public function testAddThrowsExceptionForInvalidTarget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->router->add('/', ['controller' => 'MainController']);
    }

    // add(): обработка пустого target
    public function testAddWithInvalidArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->router->add('', []);
    }

    // push(): массовое добавление маршрутов
    public function testPushRoutes(): void
    {
        $routes = [
            '/contact' => ['controller' => 'TestController', 'action' => 'contact'],
            '/services' => ['controller' => 'TestController', 'action' => 'services'],
        ];

        $this->router->push($routes);
        $allRoutes = $this->router->get();

        foreach ($routes as $uri => $target) {
            $this->assertArrayHasKey($uri, $allRoutes);
            $this->assertSame($target, $allRoutes[$uri]);
        }
    }

    // push(): перезапись существующих маршрутов
    public function testPushOverwritesExistingRoutes(): void
    {
        $this->router->add('/test', ['controller' => 'OldController', 'action' => 'old']);

        $routes = [
            '/test' => ['controller' => 'NewController', 'action' => 'new'],
            '/new' => ['controller' => 'AnotherController', 'action' => 'another'],
        ];

        $this->router->push($routes);
        $allRoutes = $this->router->get();

        $this->assertSame('NewController', $allRoutes['/test']['controller']);
        $this->assertSame('AnotherController', $allRoutes['/new']['controller']);
    }

    // get(): получение всех маршрутов
    public function testGetAllRoutes(): void
    {
        $routes = [
            '/' => ['controller' => 'TestController', 'action' => 'index'],
            '/test' => ['controller' => 'TestController', 'action' => 'index'],
        ];

        $this->router->push($routes);
        $allRoutes = $this->router->get();

        $this->assertCount(2, $allRoutes);
        $this->assertArrayHasKey('/test', $allRoutes);
    }

    // get(): получение конкретного маршрута
    public function testGetSpecificRoute(): void
    {
        $routes = ['/' => ['controller' => 'TestController', 'action' => 'index']];

        $this->router->add('/', $routes['/']);
        $specificRoute = $this->router->get('/');

        $this->assertSame($routes['/'], $specificRoute);
    }

    // get(): пустой список маршрутов
    public function testGetReturnsEmptyArrayIfNoRoutesAdded(): void
    {
        $routes = $this->router->get();
        $this->assertEmpty($routes);
    }

    // get(): несуществующий маршрут
    public function testGetReturnsNullForNonExistentRoute(): void
    {
        $this->router->add('/test', ['controller' => 'TestController', 'action' => 'index']);
        $result = $this->router->get('/non-existent');

        $this->assertNull($result);
    }

    // match(): точное совпадение маршрута
    public function testMatchExactRoute(): void
    {
        $uri = '/';
        $target = ['controller' => 'TestController', 'action' => 'index'];

        $this->router->add($uri, $target);
        $matched = $this->router->match('/');

        $this->assertSame($target['controller'], $matched['controller']);
        $this->assertSame($target['action'], $matched['action']);
        $this->assertEmpty($matched['parameters'] ?? []);
    }

    // match(): маршрут с параметрами
    public function testMatchRouteWithParameters(): void
    {
        $this->router->add('/user/{id}', ['controller' => 'UserController', 'action' => 'show']);
        $match = $this->router->match('/user/123');

        $this->assertEquals([
            'controller' => 'UserController',
            'action' => 'show',
            'parameters' => ['id' => '123']
        ], $match);
    }

    // match(): конфликтующие маршруты
    public function testMatchRoutesWithConflicting(): void
    {
        $this->router->add('/user/{id}', ['controller' => 'UserController', 'action' => 'show']);
        $this->router->add('/user/list', ['controller' => 'UserController', 'action' => 'list']);
    
        $match = $this->router->match('/user/list');

        $this->assertSame('UserController', $match['controller']);
        $this->assertSame('list', $match['action']);
    }

    // match(): несуществующий маршрут
    public function testMatchThrowsExceptionIfRouteNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->router->match('/non-existent');
    }

    // match(): производительность
    public function testMatchPerformance(): void
    {
        for ($i = 0; $i < 10000; $i++) {
            $this->router->add("/test{$i}", [
                'controller' => 'TestController',
                'action' => 'action'
            ]);
        }
        
        $start = microtime(true);
        $this->router->match('/test9999');
        $end = microtime(true);
        $this->assertLessThan(1, $end - $start, 'Matching route took too long.');
    }

    // resolveController(): базовый случай
    public function testResolveController(): void
    {
        $class = new class ($this->container) extends Controller { public function index() {} };
        class_alias(get_class($class), 'App\\Controllers\\TestController');

        $match = ['controller' => 'TestController', 'action' => 'index'];

        $controller = $this->router->resolveController($match);

        $this->assertIsObject($controller);
        $this->assertInstanceOf('App\\Controllers\\TestController', $controller);
        $this->assertTrue(method_exists($controller, 'index'));
    }

    // resolveController(): несуществующий контроллер
    public function testResolveControllerWithInvalidController(): void
    {
        $this->expectException(RuntimeException::class);

        $match = ['controller' => 'NonExistentController', 'action' => 'index'];
        $this->router->resolveController($match);
    }

    // resolveController(): отсутствует контроллер в маршруте
    public function testResolveControllerThrowsExceptionWhenControllerIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No controller defined in route.");

        $this->router->resolveController(['action' => 'index']);
    }

    // resolveController(): отсутствует действие в маршруте
    public function testResolveControllerThrowsExceptionIfActionIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No action defined in route.");

        $this->router->resolveController(['controller' => 'TestController']);
    }

    // resolveController(): класс контроллера не найден
    public function testResolveControllerThrowsExceptionIfClassNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/Controller class not found/");

        $tempDir = sys_get_temp_dir();
        $controllerPath = "{$tempDir}/EmptyController.php";
        file_put_contents($controllerPath, "<?php // Empty file with no class");

        $match = ['controller' => 'EmptyController', 'action' => 'index'];

        try {
            $this->router->resolveController($match);
        } finally {
            unlink($controllerPath);
        }
    }

    // resolveController(): контроллер не наследует базовый класс
    public function testResolveControllerThrowsExceptionIfNotExtendingBaseController(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Controller does not extend the base Controller class.");

        $match = ['controller' => 'InvalidController', 'action' => 'index'];
        $controller = new class {};

        class_alias(get_class($controller), 'App\\Controllers\\InvalidController');

        $this->router->resolveController($match);
    }

    // dispatch(): успешный вызов действия
    public function testDispatchCallsControllerActionSuccessfully(): void
    {
        $controller = new class ($this->container) extends Controller {
            public array $match = ['action' => 'index', 'parameters' => []];
            public function index() { return "Action executed"; }
        };

        $data = $this->router->dispatch($controller);

        $this->assertSame('Action executed', $data);
    }

    // dispatch(): частичные параметры
    public function testDispatchHandlesPartialParameters(): void
    {
        $controller = new class ($this->container) extends Controller {
            public array $match = [
                'action' => 'index',
                'parameters' => ['param1' => 'value1']
            ];
            public function index(string $param1 = 'default1', string $param2 = 'default2')
            {
                return "param1 = {$param1}, param2 = {$param2}";
            }
        };

        $data = $this->router->dispatch($controller);

        $this->assertSame("param1 = value1, param2 = default2", $data);
    }

    // dispatch(): отсутствует метод действия
    public function testDispatchThrowsExceptionIfActionMethodIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/Action method 'missing' not found in controller/");

        $controller = new class ($this->container) extends Controller {
            public array $match = ['action' => 'missing', 'parameters' => []];
        };

        $this->router->dispatch($controller);
    }

    // Вспомогательные тесты для контроллера
    public function testControllerWithShowMethodAndParameter(): void
    {
        $controller = new class ($this->container) extends Controller {
            public array $match = ['action' => 'show', 'parameters' => []];
            public function show(string $id) { return "Test result {$id}"; }
        };

        $result = $controller->show('123');
        
        $this->assertEquals('Test result 123', $result);
    }
}
