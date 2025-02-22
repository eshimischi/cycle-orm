<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Typecast;

use Cycle\ORM\Heap\Node;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Parser\Typecast;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Select;
use Cycle\ORM\Tests\Fixtures\Admin;
use Cycle\ORM\Tests\Fixtures\JsonSerializableClass;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Fixtures\User;
use Cycle\ORM\Tests\Functional\Driver\Common\Typecast\Fixture\JsonTypecast;
use Cycle\ORM\Tests\Traits\TableTrait;

abstract class JsonTest extends BaseTest
{
    use TableTrait;

    public function testFetchAll(): void
    {
        $selector = new Select($this->orm, User::class);
        $result = $selector->fetchAll();

        $this->assertInstanceOf(User::class, $result[0]);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame('hello@world.com', $result[0]->email);
        $this->assertSame(['theme' => 'dark'], $result[0]->settings);
        $this->assertNull($result[0]->settingsNullable);
        $this->assertNull($result[0]->jsonSerializable);

        $this->assertInstanceOf(User::class, $result[1]);
        $this->assertSame(2, $result[1]->id);
        $this->assertSame('another@world.com', $result[1]->email);
        $this->assertSame(['grids' => ['products' => ['columns' => ['id', 'title']]]], $result[1]->settings);
        $this->assertSame(['theme' => 'dark'], $result[1]->settingsNullable);
        $this->assertNull($result[1]->jsonSerializable);
    }

    public function testNoWrite(): void
    {
        $selector = new Select($this->orm, User::class);
        $result = $selector->fetchOne();

        $this->captureWriteQueries();
        $this->save($result);
        $this->assertNumWrites(0);
    }

    public function testStore(): void
    {
        $e = new User();
        $e->email = 'test@email.com';
        $e->settings = ['theme' => 'light'];
        $e->settingsNullable = ['some' => 'data'];

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(1);

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(0);

        $this->assertTrue($this->orm->getHeap()->has($e));
        $this->assertSame(Node::MANAGED, $this->orm->getHeap()->get($e)->getStatus());

        $this->orm->getHeap()->clean();

        $selector = new Select($this->orm, User::class);
        $result = $selector->where('id', $e->id)->fetchOne();
        $this->assertSame('test@email.com', $result->email);
        $this->assertSame(['theme' => 'light'], $result->settings);
        $this->assertSame(['some' => 'data'], $result->settingsNullable);
    }

    /**
     * @throws \Throwable
     */
    public function testStoresEmptyArraySettings(): void
    {
        $e = new User();
        $e->email = 'test@email.com';
        $e->settings = [];
        $e->settingsNullable = [];

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(1);

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(0);

        $this->assertTrue($this->orm->getHeap()->has($e));
        $this->assertSame(Node::MANAGED, $this->orm->getHeap()->get($e)->getStatus());

        $this->orm->getHeap()->clean();

        $selector = new Select($this->orm, User::class);
        $result = $selector->where('id', $e->id)->fetchOne();
        $this->assertSame('test@email.com', $result->email);
        $this->assertSame([], $result->settings);
        $this->assertSame([], $result->settingsNullable);
    }

    public function testStoresNullSettings(): void
    {
        $e = new User();
        $e->email = 'test@email.com';
        $e->settings = [];
        $e->settingsNullable = null;

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(1);

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(0);

        $this->assertTrue($this->orm->getHeap()->has($e));
        $this->assertSame(Node::MANAGED, $this->orm->getHeap()->get($e)->getStatus());

        $this->orm->getHeap()->clean();

        $selector = (new Select($this->orm, User::class))->where('id', $e->id);
        $result = $selector->fetchOne();
        $this->assertSame('test@email.com', $result->email);
        $this->assertSame([], $result->settings);
        $this->assertSame(null, $result->settingsNullable);

        $result = $selector->fetchData(false)[0];
        $this->assertNull($result['settingsNullable']);
    }

    public function testStoreJsonSerializable(): void
    {
        $e = new User();
        $e->email = 'test@email.com';
        $e->settings = ['theme' => 'light'];
        $e->jsonSerializable = new JsonSerializableClass();

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(1);

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(0);

        $this->assertTrue($this->orm->getHeap()->has($e));
        $this->assertSame(Node::MANAGED, $this->orm->getHeap()->get($e)->getStatus());

        $this->orm->getHeap()->clean();

        $result = $this->getDatabase()->table('users')->select()->where('id', $e->id)->fetchAll();
        $this->assertEquals(
            (new JsonSerializableClass())->jsonSerialize(),
            \json_decode($result[0]['json_serializable'], true),
        );
    }

    public function testUpdate(): void
    {
        $e = $this->orm->get('user', ['id' => 1]);
        $e->settings = ['theme' => 'light'];

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(1);

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(0);

        $this->orm->getHeap()->clean();

        $selector = new Select($this->orm, User::class);
        $result = $selector->where('id', 1)->fetchOne();
        $this->assertSame(['theme' => 'light'], $result->settings);
    }

    public function testOverrideTypecast(): void
    {
        $selector = new Select($this->orm, Admin::class);
        $result = $selector->fetchAll();

        $this->assertSame(['json'], $result[0]->settings);

        $e = new Admin();
        $e->email = 'test@email.com';
        $e->settings = ['theme' => 'light'];

        $this->captureWriteQueries();
        $this->save($e);
        $this->assertNumWrites(1);

        $this->orm->getHeap()->clean();

        $result = $this->getDatabase()->table('users')->select()->where('id', $e->id)->fetchAll();
        $this->assertSame('uncast-json', $result[0]['settings']);
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTable(table: 'users', columns: [
            'id' => 'primary',
            'email' => 'string',
            'settings' => 'text',
            'settings_nullable' => 'text,nullable',
            'json_serializable' => 'text,nullable',
        ], defaults: ['settings' => null, 'settings_nullable' => null, 'json_serializable' => null]);

        $this->getDatabase()->table('users')->insertOne(
            [
                'email' => 'hello@world.com',
                'settings' => \json_encode(['theme' => 'dark']),
                'settings_nullable' => null,
                'json_serializable' => null,
            ],
        );
        $this->getDatabase()->table('users')->insertOne(
            [
                'email' => 'another@world.com',
                'settings' => \json_encode(['grids' => ['products' => ['columns' => ['id', 'title']]]]),
                'settings_nullable' => \json_encode(['theme' => 'dark']),
                'json_serializable' => null,
            ],
        );

        $mapping = [
            SchemaInterface::ROLE => 'user',
            SchemaInterface::MAPPER => Mapper::class,
            SchemaInterface::DATABASE => 'default',
            SchemaInterface::TABLE => 'users',
            SchemaInterface::PRIMARY_KEY => 'id',
            SchemaInterface::COLUMNS => [
                'id' => 'id',
                'email' => 'email',
                'settings' => 'settings',
                'settingsNullable' => 'settings_nullable',
                'jsonSerializable' => 'json_serializable',
            ],
            SchemaInterface::TYPECAST => [
                'id' => 'int',
                'settings' => 'json',
                'settingsNullable' => 'json',
                'jsonSerializable' => 'json',
            ],
            SchemaInterface::SCHEMA => [],
            SchemaInterface::RELATIONS => [],
        ];

        $this->orm = $this->withSchema(new Schema([
            User::class => $mapping,
            Admin::class => [
                SchemaInterface::ROLE => 'admin',
                SchemaInterface::TYPECAST_HANDLER => [
                    JsonTypecast::class,
                    Typecast::class,
                ],
            ] + $mapping,
        ]));
    }
}
