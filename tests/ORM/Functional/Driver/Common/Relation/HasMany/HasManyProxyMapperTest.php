<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Relation\HasMany;

use Cycle\ORM\Heap\Heap;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Reference\ReferenceInterface;
use Cycle\ORM\Relation;
use Cycle\ORM\Schema;
use Cycle\ORM\Select;
use Cycle\ORM\Select\JoinableLoader;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Fixtures\Comment;
use Cycle\ORM\Tests\Fixtures\SortByIDScope;
use Cycle\ORM\Tests\Fixtures\User;
use Cycle\ORM\Tests\Traits\TableTrait;

abstract class HasManyProxyMapperTest extends BaseTest
{
    use TableTrait;

    public function testFetchRelation(): void
    {
        $selector = new Select($this->orm, User::class);
        $selector->load('comments')->orderBy('user.id');

        $this->assertEquals([
            [
                'id' => 1,
                'email' => 'hello@world.com',
                'balance' => 100.0,
                'comments' => [
                    [
                        'id' => 1,
                        'user_id' => 1,
                        'message' => 'msg 1',
                    ],
                    [
                        'id' => 2,
                        'user_id' => 1,
                        'message' => 'msg 2',
                    ],
                    [
                        'id' => 3,
                        'user_id' => 1,
                        'message' => 'msg 3',
                    ],
                ],
            ],
            [
                'id' => 2,
                'email' => 'another@world.com',
                'balance' => 200.0,
                'comments' => [],
            ],
        ], $selector->fetchData());
    }

    public function testPromised(): void
    {
        /** @var User $u */
        $u = (new Select($this->orm, User::class))->wherePK(1)->fetchOne();
        $uData = $this->extractEntity($u);

        $this->assertInstanceOf(ReferenceInterface::class, $uData['comments']);
        $this->captureReadQueries();
        $this->assertCount(3, $u->comments);
        $this->assertInstanceOf(Comment::class, $u->comments[0]);
        $this->assertNumReads(1);
    }

    public function testHasManyPromiseLoaded(): void
    {
        /** @var User $u */
        $u = (new Select($this->orm, User::class))->wherePK(1)->fetchOne();
        $uData = $this->extractEntity($u);
        $r = $uData['comments'];

        $this->captureReadQueries();
        $this->assertInstanceOf(ReferenceInterface::class, $r);
        $this->assertNumReads(0);

        $this->assertFalse($r->hasValue());
        $this->assertIsIterable($u->comments);
        $this->assertNumReads(1);
        $this->assertTrue($r->hasValue());
    }

    public function testHasManyPromiseRole(): void
    {
        /** @var User $u */
        $u = (new Select($this->orm, User::class))->wherePK(1)->fetchOne();
        $uData = $this->extractEntity($u);
        $r = $uData['comments'];

        $this->captureReadQueries();
        $this->assertInstanceOf(ReferenceInterface::class, $r);
        $this->assertNumReads(0);

        $this->assertSame('comment', $r->getRole());
    }

    public function testHasManyPromiseScope(): void
    {
        /** @var User $u */
        $u = (new Select($this->orm, User::class))->wherePK(1)->fetchOne();
        $uData = $this->extractEntity($u);
        $r = $uData['comments'];

        $this->captureReadQueries();
        $this->assertInstanceOf(ReferenceInterface::class, $r);
        $this->assertNumReads(0);

        $this->assertEquals([
            'user_id' => 1,
        ], $r->getScope());
    }

    public function testPromisedEmpty(): void
    {
        /** @var User $u */
        $u = (new Select($this->orm, User::class))->wherePK(2)->fetchOne();
        $uData = $this->extractEntity($u);
        $r = $uData['comments'];

        $this->captureReadQueries();
        $this->assertInstanceOf(ReferenceInterface::class, $r);
        $this->assertCount(0, $u->comments);
        $this->assertNumReads(1);
    }

    public function testNoChanges(): void
    {
        /** @var User $u */
        $u = (new Select($this->orm, User::class))->wherePK(1)->fetchOne();


        $this->captureReadQueries();
        $this->captureWriteQueries();
        $this->save($u);
        $this->assertNumWrites(0);
        $this->assertNumReads(0);
    }

    public function testNoChangesWithNoChildren(): void
    {
        $u = (new Select($this->orm, User::class))
            ->wherePK(2)->fetchOne();

        $this->captureReadQueries();
        $this->captureWriteQueries();
        $this->save($u);

        $this->assertNumWrites(0);
        $this->assertNumReads(0);
    }

    public function testRemoveChildren(): void
    {
        $selector = new Select($this->orm, User::class);

        /** @var User $e */
        $e = $selector->wherePK(1)->fetchOne();

        $e->comments->remove(1);

        $this->save($e);

        /** @var User $e */
        $e = (new Select($this->orm->withHeap(new Heap()), User::class))->wherePK(1)->fetchOne();

        $this->assertCount(2, $e->comments);

        $this->assertSame('msg 1', $e->comments[0]->message);
        $this->assertSame('msg 3', $e->comments[1]->message);
    }

    public function testAddAndRemoveChildren(): void
    {
        /** @var User $e */
        $e = (new Select($this->orm, User::class))
            ->wherePK(1)->fetchOne();

        $e->comments->remove(1);

        $c = new Comment();
        $c->message = 'msg 4';
        $e->comments->add($c);

        $this->save($e);

        /** @var User $e */
        $e = (new Select($this->orm->withHeap(new Heap()), User::class))->wherePK(1)->fetchOne();

        $this->assertCount(3, $e->comments);

        $this->assertSame('msg 1', $e->comments[0]->message);
        $this->assertSame('msg 3', $e->comments[1]->message);
        $this->assertSame('msg 4', $e->comments[2]->message);
    }

    public function testSliceAndSaveToAnotherParent(): void
    {
        /**
         * @var User $a
         * @var User $b
         */
        [$a, $b] = (new Select($this->orm, User::class))->orderBy('user.id')->fetchAll();

        $this->assertCount(3, $a->comments);
        $this->assertCount(0, $b->comments);

        $b->comments = $a->comments->slice(0, 2);
        foreach ($b->comments as $c) {
            $a->comments->removeElement($c);
        }

        $b->comments[0]->message = 'new b';

        $this->assertCount(1, $a->comments);
        $this->assertCount(2, $b->comments);

        $this->captureWriteQueries();
        $this->save($a, $b);
        $this->assertNumWrites(2);

        // consecutive
        $this->captureWriteQueries();
        $this->save($a, $b);
        $this->assertNumWrites(0);

        /**
         * @var User $a
         * @var User $b
         */
        [$a, $b] = (new Select($this->orm->withHeap(new Heap()), User::class))
            ->load('comments', [
                'method' => JoinableLoader::INLOAD,
                'as' => 'comment',
            ])->orderBy('user.id')->fetchAll();

        $this->assertCount(1, $a->comments);
        $this->assertCount(2, $b->comments);

        $this->assertEquals(3, $a->comments[0]->id);
        $this->assertEquals(1, $b->comments[0]->id);
        $this->assertEquals(2, $b->comments[1]->id);

        $this->assertEquals('new b', $b->comments[0]->message);
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

        $this->makeTable('comment', [
            'id' => 'primary',
            'user_id' => 'integer',
            'message' => 'string',
        ]);

        $this->makeFK('comment', 'user_id', 'user', 'id');

        $this->getDatabase()->table('comment')->insertMultiple(
            ['user_id', 'message'],
            [
                [1, 'msg 1'],
                [1, 'msg 2'],
                [1, 'msg 3'],
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
                    'comments' => [
                        Relation::TYPE => Relation::HAS_MANY,
                        Relation::TARGET => Comment::class,
                        Relation::LOAD => Relation::LOAD_PROMISE,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::INNER_KEY => 'id',
                            Relation::OUTER_KEY => 'user_id',
                        ],
                    ],
                ],
            ],
            Comment::class => [
                Schema::ROLE => 'comment',
                Schema::MAPPER => Mapper::class,
                Schema::DATABASE => 'default',
                Schema::TABLE => 'comment',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS => ['id', 'user_id', 'message'],
                Schema::SCHEMA => [],
                Schema::RELATIONS => [],
                Schema::SCOPE => SortByIDScope::class,
            ],
        ]));
    }
}
