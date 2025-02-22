<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Typecast;

use Cycle\ORM\Collection\ArrayCollectionFactory;
use Cycle\ORM\Config\RelationConfig;
use Cycle\ORM\Exception\FactoryTypecastException;
use Cycle\ORM\Factory;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\ORM;
use Cycle\ORM\Parser\CastableInterface;
use Cycle\ORM\Parser\CompositeTypecast;
use Cycle\ORM\Parser\Typecast;
use Cycle\ORM\Parser\TypecastInterface;
use Cycle\ORM\Service\TypecastProviderInterface;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Tests\Functional\Driver\Common\Typecast\Fixture\Book;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Functional\Driver\Common\Typecast\Fixture\InvalidTypecaster;
use Cycle\ORM\Tests\Functional\Driver\Common\Typecast\Fixture\Typecaster;
use Cycle\ORM\Tests\Util\SimpleFactory;
use Spiral\Core\Container;

final class SchemaTest extends BaseTest
{
    public const DRIVER = 'sqlite';
    private const PRIMARY_ROLE = 'book';

    private ?Container $container;

    public function setUpOrm(array $bookSchema = [], array $factoryDefinitions = []): void
    {
        $container = $this->container ??= new Container();

        $this->orm = new ORM(
            new Factory(
                $this->dbal,
                RelationConfig::getDefault(),
                new SimpleFactory(
                    $factoryDefinitions,
                    static fn(string|object $alias, array $parameters = []): mixed => \is_string($alias)
                        ? $container->make($alias, $parameters) : $alias,
                ),
                new ArrayCollectionFactory(),
            ),
            new Schema([
                Book::class => $bookSchema + [
                    SchemaInterface::ROLE => self::PRIMARY_ROLE,
                    SchemaInterface::MAPPER => Mapper::class,
                    SchemaInterface::DATABASE => 'default',
                    SchemaInterface::TABLE => 'book',
                    SchemaInterface::PRIMARY_KEY => 'id',
                    SchemaInterface::COLUMNS => ['id', 'states', 'nested_states', 'published_at'],
                    SchemaInterface::RELATIONS => [],
                ],
                'foo' => [
                    SchemaInterface::MAPPER => Mapper::class,
                    SchemaInterface::DATABASE => 'default',
                    SchemaInterface::TABLE => 'foo',
                    SchemaInterface::PRIMARY_KEY => 'id',
                    SchemaInterface::COLUMNS => ['id', 'foo'],
                ],
                'bar' => [
                    SchemaInterface::MAPPER => Mapper::class,
                    SchemaInterface::DATABASE => 'default',
                    SchemaInterface::TABLE => 'bar',
                    SchemaInterface::PARENT => 'foo',
                    SchemaInterface::PRIMARY_KEY => 'id',
                    SchemaInterface::COLUMNS => ['id', 'bar'],
                    SchemaInterface::TYPECAST => ['id' => 'int'],
                ],
                'baz' => [
                    SchemaInterface::MAPPER => Mapper::class,
                    SchemaInterface::DATABASE => 'default',
                    SchemaInterface::TABLE => 'baz',
                    SchemaInterface::PARENT => 'bar',
                    SchemaInterface::PRIMARY_KEY => 'id',
                    SchemaInterface::COLUMNS => ['id', 'baz'],
                    SchemaInterface::TYPECAST => ['baz' => 'int'],
                ],
            ]),
        );
    }

    public function testEmptyStringShouldThrowAnException(): void
    {
        $this->expectException(FactoryTypecastException::class);
        $this->expectExceptionMessageMatches(
            '/Bad typecast handler declaration for the `book` role./',
        );

        $this->setUpOrm([
            SchemaInterface::TYPECAST_HANDLER => '',
        ]);

        $this->getTypecast(self::PRIMARY_ROLE);
    }

    public function testHandlerWithWrongInterfaceShouldThrowAnException(): void
    {
        $this->setUpOrm([
            SchemaInterface::TYPECAST_HANDLER => InvalidTypecaster::class,
        ]);

        $this->expectException(FactoryTypecastException::class);
        $this->expectExceptionMessage(
            'Bad typecast handler declaration for the `book` role. Cycle\ORM\Factory::makeTypecastHandler(): Return value must be of type Cycle\ORM\Parser\TypecastInterface, Cycle\ORM\Tests\Functional\Driver\Common\Typecast\Fixture\InvalidTypecaster returned',
        );

        $this->orm->getService(TypecastProviderInterface::class)->getTypecast(self::PRIMARY_ROLE);
    }

    public function testHandlerWithWrongInterfaceAmongArrayShouldThrowAnException(): void
    {
        $this->container = new Container();
        $this->container->bind('bar-foo', Typecaster::class);
        $this->setUpOrm([
            SchemaInterface::TYPECAST_HANDLER => [
                InvalidTypecaster::class,
                'bar-foo',
            ],
        ]);

        $this->expectException(FactoryTypecastException::class);
        $this->expectExceptionMessage(
            'Bad typecast handler declaration for the `book` role. Cycle\ORM\Factory::makeTypecastHandler(): Return value must be of type Cycle\ORM\Parser\TypecastInterface, Cycle\ORM\Tests\Functional\Driver\Common\Typecast\Fixture\InvalidTypecaster returned',
        );

        $this->orm->getService(TypecastProviderInterface::class)->getTypecast(self::PRIMARY_ROLE);
    }

    public function testUseTypecastFromContainer(): void
    {
        $this->container = new Container();
        $this->container->bind('bar-foo', Typecaster::class);

        $this->setUpOrm([
            SchemaInterface::TYPECAST_HANDLER => 'bar-foo',
        ]);

        $typecast = $this->getTypecast(self::PRIMARY_ROLE);
        $this->assertSame(Typecaster::class, $typecast::class);
    }

    public function testUseTypecastAliasAsString(): void
    {
        $tc = new Typecaster(new Schema([]), self::PRIMARY_ROLE);
        $this->setUpOrm([
            SchemaInterface::TYPECAST_HANDLER => 'test-alias',
        ], ['test-alias' => &$tc]);

        $typecast = $this->getTypecast(self::PRIMARY_ROLE);

        $this->assertSame(Typecaster::class, $typecast::class);
    }

    public function testUseTypecastClassAsString(): void
    {
        $options = ['id' => 'int', 'published_at' => 'datetime(d-m-Y-H-i-s-u)'];
        $this->setUpOrm([
            SchemaInterface::TYPECAST => $options,
            SchemaInterface::TYPECAST_HANDLER => Typecaster::class,
        ]);

        /** @var Typecaster $typecast */
        $typecast = $this->getTypecast(self::PRIMARY_ROLE);

        $this->assertSame(Typecaster::class, $typecast::class);
        $this->assertSame(self::PRIMARY_ROLE, $typecast->role);
        $this->assertSame($options, $typecast->rules);
    }

    public function testNullDefinitionAndEmptyRules(): void
    {
        $this->setUpOrm([
            SchemaInterface::TYPECAST_HANDLER => null,
        ]);

        $typecast = $this->getTypecast(self::PRIMARY_ROLE);

        $this->assertNull($typecast);
    }

    public function testTypecastWithJti(): void
    {
        $this->setUpOrm();

        $foo = $this->getTypecast('foo');
        $bar = $this->getTypecast('bar');
        $baz = $this->getTypecast('baz');

        $this->assertNull($foo);
        $this->assertSame(Typecast::class, $bar::class);
        $this->assertSame(CompositeTypecast::class, $baz::class);
    }

    /**
     * @see CompositeTypecast::cast()
     */
    public function testUseCompositeTypecast(): void
    {
        $containerTypecast = $this->createMock(CastableInterface::class);
        $containerTypecast
            ->expects($this->exactly(1))
            ->method('cast')
            ->with($this->equalTo(['foo' => 'bar'])) // waits from factory
            ->willReturn(['foo' => 'bar3']); // passes to $aliasTypecast

        $aliasTypecast = $this->createMock(CastableInterface::class);
        $aliasTypecast
            ->expects($this->exactly(1))
            ->method('cast')
            ->with($this->equalTo(['foo' => 'bar3'])) // waits from $containerTypecast
            ->willReturn(['foo' => 'bar1']); // passes to $typecast

        $typecast = $this->createMock(CastableInterface::class);
        $typecast
            ->expects($this->exactly(1))
            ->method('cast')
            ->with($this->equalTo(['foo' => 'bar1'])) // waits from $aliasTypecast
            ->willReturn(['foo' => 'bar2']); // passes to Composite typecast

        $this->container = new Container();
        $this->container->bindSingleton('bar-foo', fn() => $containerTypecast);

        $this->setUpOrm([
            SchemaInterface::TYPECAST_HANDLER => [
                'bar-foo', // container
                'test-alias', // alias
                $typecast, // class string
            ],
        ], [
            'test-alias' => $aliasTypecast,
        ]);

        $typecast = $this->getTypecast(self::PRIMARY_ROLE);

        $this->assertSame(CompositeTypecast::class, $typecast::class);
        $this->assertSame(['foo' => 'bar2'], $typecast->cast(['foo' => 'bar']));
    }

    private function getTypecast(string $role): ?TypecastInterface
    {
        return $this->orm->getService(TypecastProviderInterface::class)->getTypecast($role);
    }
}
