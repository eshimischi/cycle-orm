<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Unit\Mapper\Proxy;

use Cycle\ORM\EntityProxyInterface;
use Cycle\ORM\FactoryInterface;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Mapper\Proxy\Hydrator\ClassPropertiesExtractor;
use Cycle\ORM\Mapper\Proxy\Hydrator\ClosureHydrator;
use Cycle\ORM\Mapper\Proxy\ProxyEntityFactory;
use Cycle\ORM\ORM;
use Cycle\ORM\RelationMap;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase;

class ProxyEntityFactoryTest extends TestCase
{
    private ProxyEntityFactory $factory;
    private ORM $orm;

    public function testCreatesObject()
    {
        $user = $this->factory->create(RelationMap::build($this->orm, 'user'), User::class);

        $this->assertInstanceOf(EntityProxyInterface::class, $user);
    }

    public function testCreatesNonExistingObjectShouldThrowException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The entity `hello-world` class does not exist. Proxy factory can not create classless entities.',
        );

        $this->factory->create(RelationMap::build($this->orm, 'user'), 'hello-world');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new ProxyEntityFactory(
            new ClosureHydrator(),
            new ClassPropertiesExtractor(),
        );

        $factory = $this->createMock(FactoryInterface::class);

        $this->orm = new ORM(
            $factory,
            new Schema([
                'user' => [
                    SchemaInterface::ENTITY => User::class,
                    SchemaInterface::MAPPER => Mapper::class,
                    SchemaInterface::DATABASE => 'default',
                    SchemaInterface::TABLE => 'user',
                    SchemaInterface::PRIMARY_KEY => 'id',
                    SchemaInterface::COLUMNS => ['id', 'email', 'balance'],
                    SchemaInterface::SCHEMA => [],
                    SchemaInterface::RELATIONS => [],
                ],
            ]),
        );
    }
}
