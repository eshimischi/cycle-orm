<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Relation;

use Cycle\ORM\Heap\Heap;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Relation;
use Cycle\ORM\Schema;
use Cycle\ORM\Select;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Fixtures\Profile;
use Cycle\ORM\Tests\Fixtures\SortByIDScope;
use Cycle\ORM\Tests\Fixtures\User;
use Cycle\ORM\Tests\Traits\TableTrait;
use Cycle\ORM\Transaction;

abstract class InverseRelationTest extends BaseTest
{
    use TableTrait;

    public function testFetchRelation(): void
    {
        $selector = new Select($this->orm, User::class);
        $selector->load('profile.user')->orderBy('user.id');

        $this->assertEquals([
            [
                'id' => 1,
                'email' => 'hello@world.com',
                'balance' => 100.0,
                'profile' => [
                    'id' => 1,
                    'user_id' => 1,
                    'image' => 'image.png',
                    'user' => [
                        'id' => 1,
                        'email' => 'hello@world.com',
                        'balance' => 100.0,
                    ],
                ],
            ],
            [
                'id' => 2,
                'email' => 'another@world.com',
                'balance' => 200.0,
                'profile' => [
                    'id' => 2,
                    'user_id' => 2,
                    'image' => 'second.png',
                    'user' => [
                        'id' => 2,
                        'email' => 'another@world.com',
                        'balance' => 200.0,
                    ],
                ],
            ],
        ], $selector->fetchData());
    }

    public function testSelfReferenceEntity(): void
    {
        $selector = new Select($this->orm, User::class);
        $selector->load('profile.user')->orderBy('user.id');

        [$a, $b] = $selector->fetchAll();

        $this->assertSame($a, $a->profile->user);
        $this->assertSame($b, $b->profile->user);
    }

    public function testCyclicThoughtInverse(): void
    {
        $u = new User();
        $u->email = 'cyclic@email.com';
        $u->balance = 700;
        $u->profile = new Profile();
        $u->profile->image = 'sample.gif';

        // cyclic
        $u->profile->user = $u;

        $this->captureWriteQueries();

        $tr = new Transaction($this->orm);
        $tr->persist($u);
        $tr->run();

        $this->assertNumWrites(2);

        $this->assertEquals(3, $u->id);
        $this->assertEquals(4, $u->profile->id);

        $selector = new Select($this->orm->withHeap(new Heap()), User::class);
        $u = $selector->load('profile.user')->wherePK(3)->fetchOne();

        $this->assertSame($u, $u->profile->user);

        $this->orm = $this->orm->withHeap(new Heap());
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTable('user', [
            'id' => 'primary',
            'email' => 'string',
            'balance' => 'float',
        ]);

        $this->getDatabase()->table('user')->insertMultiple(
            ['email', 'balance'],
            [
                ['hello@world.com', 100],
                ['another@world.com', 200],
            ],
        );

        $this->makeTable('profile', [
            'id' => 'primary',
            'user_id' => 'integer',
            'image' => 'string',
        ]);

        $this->makeFK('profile', 'user_id', 'user', 'id');

        $this->getDatabase()->table('profile')->insertMultiple(
            ['user_id', 'image'],
            [
                [1, 'image.png'],
                [2, 'second.png'],
                [2, 'third.png'],
            ],
        );

        $this->orm = $this->withSchema(new Schema([
            User::class => [
                Schema::ROLE => 'user',
                Schema::MAPPER => Mapper::class,
                Schema::DATABASE => 'default',
                Schema::TABLE => 'user',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS => ['id', 'email', 'balance'],
                Schema::SCHEMA => [],
                Schema::RELATIONS => [
                    'profile' => [
                        Relation::TYPE => Relation::HAS_ONE,
                        Relation::TARGET => Profile::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::INNER_KEY => 'id',
                            Relation::OUTER_KEY => 'user_id',
                        ],
                    ],
                ],
                Schema::SCOPE => SortByIDScope::class,
            ],
            Profile::class => [
                Schema::ROLE => 'profile',
                Schema::MAPPER => Mapper::class,
                Schema::DATABASE => 'default',
                Schema::TABLE => 'profile',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS => ['id', 'user_id', 'image'],
                Schema::SCHEMA => [],
                Schema::RELATIONS => [
                    'user' => [
                        Relation::TYPE => Relation::BELONGS_TO,
                        Relation::TARGET => User::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::INNER_KEY => 'user_id',
                            Relation::OUTER_KEY => 'id',
                        ],
                    ],
                ],
                Schema::SCOPE => SortByIDScope::class,
            ],
        ]));
    }
}
