<?php
declare(strict_types=1);

namespace Sumish\Tests;

use PHPUnit\Framework\TestCase;
use Sumish\Container;
use Sumish\Loader;

class LoaderTest extends TestCase
{
    private Container $container;
    private Loader $loader;

    protected function setUp(): void
    {
        // Сброс счетчиков экземпляров
        TestUserModel::resetInstanceCount();
        TestPdfGenerator::resetInstanceCount();

        // Создаем алиасы для тестовых классов с префиксом Test только если они еще не существуют
        if (!class_exists('\\App\\Models\\TestUserModel')) {
            class_alias(TestUserModel::class, '\\App\\Models\\TestUserModel');
        }
        if (!class_exists('\\App\\Libraries\\TestPdfGenerator')) {
            class_alias(TestPdfGenerator::class, '\\App\\Libraries\\TestPdfGenerator');
        }
        
        $this->container = Container::create([]);
        $this->container->set('loader', new Loader($this->container));
        $this->loader = $this->container->get('loader');
    }

    // model(): базовая загрузка модели
    public function testModelLoading(): void
    {
        $userModel = $this->loader->model('TestUser');
        $this->assertInstanceOf(TestUserModel::class, $userModel);
        $this->assertEquals('Test user method', $userModel->testMethod());
    }

    // library(): базовая загрузка библиотеки
    public function testLibraryLoading(): void
    {
        $pdfLibrary = $this->loader->library('TestPdfGenerator');
        $this->assertInstanceOf(TestPdfGenerator::class, $pdfLibrary);
        $this->assertEquals('Test PDF generated', $pdfLibrary->generate());
    }

    // model(): проверка кэширования модели
    public function testModelCaching(): void
    {
        $firstModel = $this->loader->model('TestUser');
        $secondModel = $this->loader->model('TestUser');

        // Проверяем, что это один и тот же экземпляр
        $this->assertSame($firstModel, $secondModel);
        
        // Проверяем, что экземпляр создан только один раз
        $this->assertEquals(1, $firstModel->getId());
    }

    // library(): проверка кэширования библиотеки
    public function testLibraryCaching(): void
    {
        $firstLibrary = $this->loader->library('TestPdfGenerator');
        $secondLibrary = $this->loader->library('TestPdfGenerator');

        // Проверяем, что это один и тот же экземпляр
        $this->assertSame($firstLibrary, $secondLibrary);
        
        // Проверяем, что экземпляр создан только один раз
        $this->assertEquals(1, $firstLibrary->getId());
    }

    // load(): обработка исключений при загрузке несуществующих ресурсов
    public function testResourceNotFoundException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Resource class not found/');
        
        $this->loader->model('NonExistentModel');
        $this->loader->library('NonExistentLibrary');
    }
}

// Тестовая модель
class TestUserModel {
    private static $instanceCount = 0;
    private $id;

    public function __construct() {
        $this->id = ++self::$instanceCount;
    }

    public function testMethod() {
        return 'Test user method';
    }

    public function getId() {
        return $this->id;
    }

    public static function resetInstanceCount() {
        self::$instanceCount = 0;
    }
}

// Тестовая библиотека
class TestPdfGenerator {
    private static $instanceCount = 0;
    private $id;

    public function __construct() {
        $this->id = ++self::$instanceCount;
    }

    public function generate() {
        return 'Test PDF generated';
    }

    public function getId() {
        return $this->id;
    }

    public static function resetInstanceCount() {
        self::$instanceCount = 0;
    }
}
