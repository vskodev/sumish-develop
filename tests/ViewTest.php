<?php

declare(strict_types=1);

namespace Sumish\Tests;

use PHPUnit\Framework\TestCase;
use Sumish\View;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class ViewTest extends TestCase
{
    private View $view;
    private string $pathTemplate;

    protected function setUp(): void
    {
        // Создаем временную директорию для шаблонов
        $this->pathTemplate = sys_get_temp_dir() . '/views_' . uniqid();
        @mkdir($this->pathTemplate, 0777, true);
        
        $this->view = new View($this->pathTemplate);
    }

    // __construct(): инициализация с путями к шаблонам
    public function testConstructorInitializesPath(): void
    {
        $reflection = new \ReflectionClass($this->view);
        $pathProperty = $reflection->getProperty('path');
        $pathProperty->setAccessible(true);

        $this->assertStringEndsWith($this->pathTemplate, $pathProperty->getValue($this->view));
    }

    // render(): рендеринг Twig шаблона
    public function testRenderWithTwig(): void
    {
        $this->createTemplate('test.twig', 'Hello, {{ name }}!');
        
        $result = $this->view->render('test', ['name' => 'World']);
        
        $this->assertEquals('Hello, World!', $result);
    }

    // render(): рендеринг PHP шаблона
    public function testRenderWithPhp(): void
    {
        $this->createTemplate('test.php', 'Hello, <?php echo $name; ?>!');
        
        $result = $this->view->render('test', ['name' => 'World']);
        
        $this->assertEquals('Hello, World!', $result);
    }

    // render(): безопасность данных в PHP шаблоне
    public function testDataExtractionInPhpTemplate(): void
    {
        $this->createTemplate('vars.php', '<?php echo isset($internal) ? "1" : "0"; ?>');
        
        $internal = 'test';
        $result = $this->view->render('vars', ['data' => 'value']);
        
        $this->assertEquals('0', $result);
    }

    // render(): предпочтение Twig при наличии обоих шаблонов
    public function testPrefersTwigOverPhp(): void
    {
        $this->createTemplate('test.twig', 'Twig: {{ name }}');
        $this->createTemplate('test.php', 'PHP: <?php echo $name; ?>');
        
        $result = $this->view->render('test', ['name' => 'Test']);
        
        $this->assertEquals('Twig: Test', $result);
    }

    public function testRenderWithoutData(): void
    {
        $this->createTemplate('empty.twig', 'No data needed');
        $result = $this->view->render('empty');
        $this->assertEquals('No data needed', $result);
    }

    // render(): работа с вложенными директориями
    public function testRenderFromSubdirectory(): void
    {
        @mkdir($this->pathTemplate . '/admin', 0777, true);
        $this->createTemplate('admin/dashboard.twig', 'Dashboard: {{ title }}');
        
        $result = $this->view->render('admin/dashboard', ['title' => 'Admin']);
        
        $this->assertEquals('Dashboard: Admin', $result);
    }

    // render(): обработка отсутствующего шаблона
    public function testThrowsExceptionForMissingTemplate(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template file not found:');
        
        $this->view->render('nonexistent');
    }

    protected function tearDown(): void
    {
        // Удаляем созданные файлы и директории
        // array_map('unlink', glob($this->pathTemplate . '/*.*'));
        if (is_dir($this->pathTemplate)) {
            $this->clearTemplate($this->pathTemplate);
        }
    }

    private function createTemplate(string $name, string $content): void
    {
        $path = $this->pathTemplate . DIRECTORY_SEPARATOR . $name;
        $directory = dirname($path);
    
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true); // Создание вложенных директорий
        }

        file_put_contents($path, $content);
    }

    private function clearTemplate(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->clearTemplate($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
