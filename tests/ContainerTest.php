<?php

declare(strict_types=1);

namespace Sumish\Tests;

use SplStack;
use stdClass;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use PHPUnit\Framework\TestCase;
use Sumish\Container;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    // create(): инициализация с конфигурацией
    public function testCreate(): void
    {
        $testComponent = new stdClass();
        $config = [
            'components' => [
                'test' => $testComponent
            ]
        ];
        
        $container = Container::create($config);
        $this->assertTrue($container->has('config'));
        $component = $container->get('test');
        $this->assertSame($testComponent, $component);
    }

    // push(): массовое добавление компонентов
    public function testClearComponents(): void
    {
        $this->container->set('test1', new stdClass());
        $this->container->set('test2', new stdClass());
        
        $this->container->clear();
        $this->assertEmpty($this->container->list());
    }

    // register(): регистрация callable компонента
    public function testRegisterCallable(): void
    {
        $this->container->set('number', fn() => 42);
        $this->assertEquals(42, $this->container->get('number'));
    }

    // set() + get(): базовая функциональность
    public function testSetAndGet(): void
    {
        $object = new stdClass();
        $this->container->set('test', $object);
        
        $this->assertSame($object, $this->container->get('test'));
    }

    // get(): обработка отсутствующего компонента
    public function testNotFoundException(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->container->get('non_existent');
    }

    // has(): проверка наличия и удаления компонента
    public function testRemoveComponent(): void
    {
        $this->container->set('test', new stdClass());
        $this->assertTrue($this->container->has('test'));
        
        $this->container->remove('test');
        $this->assertFalse($this->container->has('test'));
    }

    // cache(): базовая функциональность с callable
    public function testCacheCallableResults(): void
    {
        $counter = 0;
        $result = $this->container->cache('test', function() use (&$counter) {
            $counter++;
            return 'result';
        });

        $this->container->cache('test', fn() => 'different');
        $this->assertEquals(1, $counter);
        $this->assertEquals('result', $result);
    }

    // cache(): работа с параметрами
    public function testCacheWithParameters(): void
    {
        $calls = 0;
        $callback = function($a, $b) use (&$calls) {
            $calls++;
            return $a + $b;
        };

        $result1 = $this->container->cache('sum', $callback, [1, 2]);
        $result2 = $this->container->cache('sum', $callback, [3, 4]);
        
        $this->assertEquals(3, $result1);
        $this->assertEquals(7, $result2);
        $this->assertEquals(2, $calls);

        $result3 = $this->container->cache('sum', $callback, [1, 2]);
        $this->assertEquals(3, $result3);
        $this->assertEquals(2, $calls);
    }

    // build(): создание класса с зависимостями
    public function testBuildClassWithDependencies(): void
    {
        $this->container->set('stdClass', stdClass::class);
        $this->container->set('component', DependentComponent::class);
        
        $component = $this->container->get('component');
        $this->assertInstanceOf(DependentComponent::class, $component);
        $this->assertInstanceOf(stdClass::class, $component->getDependency());
    }

    // build(): обработка неинстанцируемого класса
    public function testThrowsExceptionForNonInstantiableClass(): void
    {
        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage("Class 'Sumish\Tests\AbstractComponent' is not instantiable");
        
        $this->container->set('component', AbstractComponent::class);
        $this->container->get('component');
    }

    // resolveDependencies(): значения по умолчанию
    public function testDefaultValueWhenTypeNotResolved(): void
    {
        $this->container->set('component', ComponentWithDefault::class);
        $component = $this->container->get('component');
        
        $this->assertInstanceOf(ComponentWithDefault::class, $component);
        $this->assertEquals('default', $component->getValue());
    }

    // resolveDependencies(): инъекция самого контейнера
    public function testSelfInjection(): void
    {
        $this->container->set('component', ComponentWithSelfDependency::class);
        $component = $this->container->get('component');
        
        $this->assertInstanceOf(ComponentWithSelfDependency::class, $component);
        $this->assertSame($this->container, $component->getContainer());
    }

    // resolveDependencies(): обработка неразрешимого параметра
    public function testUnresolvableParameter(): void
    {
        $this->container->set('component', DateTimeComponent::class);
        
        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage('Error creating component \'component\': Unable to resolve parameter \'time\' in constructor of');
        
        $this->container->get('component');
    }

    // resolveDependencies(): union types с явными параметрами
    public function testUnionTypes(): void
    {
        $this->container->set('component', UnionTypeComponent::class, ['value' => 42]);
        
        $component = $this->container->get('component');
        $this->assertInstanceOf(UnionTypeComponent::class, $component);
        $this->assertEquals(42, $component->getValue());
    }

    // resolveDependencies(): обработка union types с пропуском builtin типов
    public function testUnionTypeBuiltinSkipAndFail(): void
    {
        $this->container->set('component', UnregisteredUnionTypeComponent::class);
        
        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage(
            "Error creating component 'component': Unable to resolve any type from union type for parameter 'value' in constructor of 'Sumish\Tests\UnregisteredUnionTypeComponent': Component 'SplStack' not found"
        );
        
        $this->container->get('component');
    }

    // resolveDependencies(): union types только с builtin типами
    public function testUnionTypesAllBuiltin(): void
    {
        $this->container->set('component', AllBuiltinUnionComponent::class, ['value' => 42]);
        
        $component = $this->container->get('component');
        $this->assertInstanceOf(AllBuiltinUnionComponent::class, $component);
        $this->assertEquals(42, $component->getValue());
    }

    // resolveDependencies(): union types с builtin и class типами
    public function testUnionTypesWithBuiltinAndClassType(): void
    {
        $this->container->set(stdClass::class, stdClass::class);
        $this->container->set('component', BuiltinAndClassUnionComponent::class);
        
        $component = $this->container->get('component');
        $this->assertInstanceOf(BuiltinAndClassUnionComponent::class, $component);
        $this->assertInstanceOf(stdClass::class, $component->getValue());
    }

    // resolveDependencies(): union types с self инъекцией
    public function testUnionTypesWithSelfInjection(): void
    {
        $this->container->set('component', SelfUnionComponent::class);
        
        $component = $this->container->get('component');
        $this->assertInstanceOf(SelfUnionComponent::class, $component);
        $this->assertInstanceOf(Container::class, $component->getValue());
    }

    // resolveDependencies(): обработка неразрешимых union types
    public function testUnionTypesWithUnresolvableTypes(): void
    {
        $this->container->set('component', DateTimeUnionComponent::class);
        
        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage('Error creating component \'component\': Unable to resolve any type from union type for parameter \'date\' in constructor of');
        
        $this->container->get('component');
    }
}

// Для тестирования создания компонента с зависимостями
class DependentComponent
{
    public function __construct(private stdClass $dependency) {}
    
    public function getDependency(): stdClass
    {
        return $this->dependency;
    }
}

// Для тестирования union types с явными параметрами
class UnionTypeComponent
{
    public function __construct(private int|string $value) {}
    
    public function getValue(): int|string
    {
        return $this->value;
    }
}

// Для тестирования обработки union types с пропуском builtin типов
class UnregisteredUnionTypeComponent
{
    private int|SplStack $value;
    
    public function __construct(int|SplStack $value)
    {
        $this->value = $value;
    }
}

// Для тестирования union types только с builtin типами
class AllBuiltinUnionComponent
{
    public function __construct(
        private int|float|string $value
    ) {}
    
    public function getValue(): int|float|string
    {
        return $this->value;
    }
}

// Для тестирования union types с builtin и class типами
class BuiltinAndClassUnionComponent
{
    private int|stdClass $value;
    
    public function __construct(int|stdClass $value)
    {
        $this->value = $value;
    }
    
    public function getValue(): int|stdClass
    {
        return $this->value;
    }
}

// Для тестирования неинстанцируемого класса
abstract class AbstractComponent
{
    abstract public function doSomething(): void;
}

// Для тестирования union types с self инъекцией
class SelfUnionComponent
{
    public function __construct(private Container|string $value) {}
    
    public function getValue(): Container|string
    {
        return $this->value;
    }
}

// Для тестирования неразрешимых union types
class DateTimeUnionComponent
{
    public function __construct(
        private DateTime|DateTimeImmutable $date
    ) {}
}

// Для тестирования значений по умолчанию
class ComponentWithDefault
{
    public function __construct(private string $value = 'default') {}
    
    public function getValue(): string
    {
        return $this->value;
    }
}

// Для тестирования инъекции контейнера
class ComponentWithSelfDependency
{
    public function __construct(private Container $container) {}
    
    public function getContainer(): Container
    {
        return $this->container;
    }
}

// Для тестирования неразрешимого параметра
class DateTimeComponent
{
    public function __construct(
        private DateTimeInterface $time
    ) {}
}
