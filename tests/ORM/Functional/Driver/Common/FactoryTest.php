<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common;

use Cycle\ORM\Exception\TypecastException;
use Cycle\ORM\Factory;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Schema;
use Cycle\ORM\Select;
use Cycle\ORM\Select\Repository;
use Cycle\ORM\Select\Source;
use Cycle\ORM\Tests\Fixtures\DifferentSource;
use Cycle\ORM\Tests\Fixtures\TimestampedMapper;
use Cycle\ORM\Tests\Fixtures\User;
use Cycle\ORM\Tests\Fixtures\UserRepository;

abstract class FactoryTest extends BaseTest
{
    /**
     * @var Factory
     */
    private $factory;

    public function testShouldMakeDefaultMapper(): void
    {
        $mapper = $this->factory->mapper($this->orm, 'user');

        $this->assertInstanceOf(Mapper::class, $mapper);
    }

    public function testShouldChangeDefaultMapperClass(): void
    {
        $this->factory = $this->factory->withDefaultSchemaClasses([
            Schema::MAPPER => TimestampedMapper::class,
        ]);

        $mapper = $this->factory->mapper($this->orm, 'user');

        $this->assertInstanceOf(TimestampedMapper::class, $mapper);
    }

    public function testShouldThrowExceptionIfDefaultMapperClassNotImplementMapperInterface(): void
    {
        $this->factory = $this->factory->withDefaultSchemaClasses([
            Schema::MAPPER => User::class,
        ]);

        $this->expectException(TypecastException::class);

        $this->factory->mapper($this->orm, 'user');
    }

    public function testShouldMakeDefaultRepository(): void
    {
        $result = $this->factory->repository(
            $this->orm,
            $this->orm->getSchema(),
            'user',
            new Select($this->orm, 'user'),
        );

        $this->assertInstanceOf(Repository::class, $result);
    }

    public function testShouldChangeDefaultRepositoryClass(): void
    {
        $this->factory = $this->factory->withDefaultSchemaClasses([
            Schema::REPOSITORY => UserRepository::class,
        ]);

        $result = $this->factory->repository(
            $this->orm,
            $this->orm->getSchema(),
            'user',
            new Select($this->orm, 'user'),
        );

        $this->assertInstanceOf(UserRepository::class, $result);
    }

    public function testShouldThrowExceptionIfDefaultRepositoryClassNotImplementRepositoryInterface(): void
    {
        $this->factory = $this->factory->withDefaultSchemaClasses([
            Schema::REPOSITORY => User::class,
        ]);

        $this->expectException(TypecastException::class);

        $this->factory->repository($this->orm, $this->orm->getSchema(), 'user', new Select($this->orm, 'user'));
    }

    public function testShouldMakeDefaultSource(): void
    {
        $result = $this->factory->source($this->orm->getSchema(), 'user');

        $this->assertInstanceOf(Source::class, $result);
    }

    public function testShouldChangeDefaultSourceClass(): void
    {
        $this->factory = $this->factory->withDefaultSchemaClasses([
            Schema::SOURCE => DifferentSource::class,
        ]);

        $result = $this->factory->source($this->orm->getSchema(), 'user');

        $this->assertInstanceOf(DifferentSource::class, $result);
    }

    public function testShouldThrowExceptionIfDefaultSourceClassNotImplementSourceInterface(): void
    {
        $this->factory = $this->factory->withDefaultSchemaClasses([
            Schema::SOURCE => User::class,
        ]);

        $this->expectException(TypecastException::class);

        $this->factory->source($this->orm->getSchema(), 'user');
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->orm = $this->withSchema(new Schema([
            User::class => [
                Schema::ROLE => 'user',
                Schema::DATABASE => 'default',
                Schema::TABLE => 'user',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS => ['id', 'email', 'balance'],
                Schema::SCHEMA => [],
                Schema::RELATIONS => [],
            ],
        ]));

        $this->factory = new Factory($this->dbal);
    }
}
