<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Relation\ManyToMany\Cyclic;

use Cycle\ORM\Relation;
use Cycle\ORM\Schema;
use Cycle\ORM\Tests\Fixtures\Tag;
use Cycle\ORM\Tests\Fixtures\TagContext;
use Cycle\ORM\Tests\Fixtures\TimestampedMapper;
use Cycle\ORM\Tests\Fixtures\User;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Traits\TableTrait;
use Cycle\ORM\Transaction;

abstract class CyclingManyToManyWithTimestampsTest extends BaseTest
{
    use TableTrait;

    public function testCreateCyclicWithExistingSingleTimestamp(): void
    {
        $u = new User();
        $u->email = 'hello@world.com';
        $u->balance = 1;

        $tag = $this->orm->getRepository(Tag::class)->findByPK(1);

        $tag->users->add($u);
        $u->tags->add($tag);

        $this->captureWriteQueries();
        $t = new Transaction($this->orm);
        $t->persist($tag);
        $t->run();
        $this->assertNumWrites(2);
    }

    public function testCreateCyclicWithNewSingleTimestamp(): void
    {
        $u = new User();
        $u->email = 'hello@world.com';
        $u->balance = 1;

        $tag = new Tag();
        $tag->name = 'new tag';

        $tag->users->add($u);
        $u->tags->add($tag);

        $this->captureWriteQueries();
        $this->save($tag);
        $this->assertNumWrites(3);
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTable(
            'user',
            [
                'id' => 'primary',
                'email' => 'string',
                'balance' => 'float',
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
            ],
        );

        $this->makeTable(
            'tag',
            [
                'id' => 'primary',
                'name' => 'string',
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
            ],
        );

        $this->makeTable(
            'tag_user_map',
            [
                'id' => 'primary',
                'user_id' => 'integer',
                'tag_id' => 'integer',
                'as' => 'string,nullable',
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
            ],
        );

        $this->makeFK('tag_user_map', 'user_id', 'user', 'id');
        $this->makeFK('tag_user_map', 'tag_id', 'tag', 'id');
        $this->makeIndex('tag_user_map', ['user_id', 'tag_id'], true);

        $this->getDatabase()->table('user')->insertMultiple(
            ['email', 'balance'],
            [
                ['hello@world.com', 100],
                ['another@world.com', 200],
            ],
        );

        $this->getDatabase()->table('tag')->insertMultiple(
            ['name'],
            [
                ['tag a'],
                ['tag b'],
                ['tag c'],
            ],
        );

        $this->getDatabase()->table('tag_user_map')->insertMultiple(
            ['user_id', 'tag_id', 'as'],
            [
                [1, 1, 'primary'],
                [1, 2, 'secondary'],
                [2, 3, 'primary'],
            ],
        );

        $this->orm = $this->withSchema(
            new Schema(
                [
                    User::class => [
                        Schema::ROLE => 'user',
                        Schema::MAPPER => TimestampedMapper::class,
                        Schema::DATABASE => 'default',
                        Schema::TABLE => 'user',
                        Schema::PRIMARY_KEY => 'id',
                        Schema::COLUMNS => ['id', 'email', 'balance'],
                        Schema::TYPECAST => ['id' => 'int', 'balance' => 'float'],
                        Schema::SCHEMA => [],
                        Schema::RELATIONS => [
                            'tags' => [
                                Relation::TYPE => Relation::MANY_TO_MANY,
                                Relation::TARGET => Tag::class,
                                Relation::LOAD => Relation::LOAD_PROMISE,
                                Relation::SCHEMA => [
                                    Relation::CASCADE => true,
                                    Relation::NULLABLE => false,
                                    Relation::THROUGH_ENTITY => TagContext::class,
                                    Relation::INNER_KEY => 'id',
                                    Relation::OUTER_KEY => 'id',
                                    Relation::THROUGH_INNER_KEY => 'user_id',
                                    Relation::THROUGH_OUTER_KEY => 'tag_id',
                                    Relation::WHERE => [],
                                    Relation::THROUGH_WHERE => [],
                                ],
                            ],
                        ],
                    ],
                    Tag::class => [
                        Schema::ROLE => 'tag',
                        Schema::MAPPER => TimestampedMapper::class,
                        Schema::DATABASE => 'default',
                        Schema::TABLE => 'tag',
                        Schema::PRIMARY_KEY => 'id',
                        Schema::COLUMNS => ['id', 'name'],
                        Schema::TYPECAST => ['id' => 'int'],
                        Schema::SCHEMA => [],
                        Schema::RELATIONS => [
                            'users' => [
                                Relation::TYPE => Relation::MANY_TO_MANY,
                                Relation::TARGET => User::class,
                                Relation::LOAD => Relation::LOAD_PROMISE,
                                Relation::SCHEMA => [
                                    Relation::CASCADE => true,
                                    Relation::NULLABLE => false,
                                    Relation::THROUGH_ENTITY => TagContext::class,
                                    Relation::INNER_KEY => 'id',
                                    Relation::OUTER_KEY => 'id',
                                    Relation::THROUGH_INNER_KEY => 'tag_id',
                                    Relation::THROUGH_OUTER_KEY => 'user_id',
                                    Relation::WHERE => [],
                                    Relation::THROUGH_WHERE => [],
                                ],
                            ],
                        ],
                    ],
                    TagContext::class => [
                        Schema::ROLE => 'tag_context',
                        Schema::MAPPER => TimestampedMapper::class,
                        Schema::DATABASE => 'default',
                        Schema::TABLE => 'tag_user_map',
                        Schema::PRIMARY_KEY => 'id',
                        Schema::COLUMNS => ['id', 'user_id', 'tag_id', 'as'],
                        Schema::TYPECAST => ['id' => 'int', 'user_id' => 'int', 'tag_id' => 'int'],
                        Schema::SCHEMA => [],
                        Schema::RELATIONS => [],
                    ],
                ],
            ),
        );
    }
}
