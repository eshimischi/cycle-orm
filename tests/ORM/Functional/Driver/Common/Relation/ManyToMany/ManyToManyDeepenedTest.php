<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Relation\ManyToMany;

use Cycle\ORM\Collection\Pivoted\PivotedCollectionInterface;
use Cycle\ORM\Heap\Heap;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Relation;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Select;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Fixtures\Image;
use Cycle\ORM\Tests\Fixtures\SortByIDScope;
use Cycle\ORM\Tests\Fixtures\Tag;
use Cycle\ORM\Tests\Fixtures\TagContext;
use Cycle\ORM\Tests\Fixtures\User;
use Cycle\ORM\Tests\Traits\TableTrait;
use Cycle\ORM\Transaction;

/**
 * This tests provides ability to create deep linked trees using pivoted entity.
 * We did not plan to have such functionality
 * but the side effect of having pivot loader to behave as normal made it possible.
 */
abstract class ManyToManyDeepenedTest extends BaseTest
{
    use TableTrait;

    public function testLoadRelation(): void
    {
        $selector = new Select($this->orm, User::class);
        $selector->load('tags.@.image')->orderBy('id', 'ASC');

        $this->assertSame([
            [
                'id' => 1,
                'email' => 'hello@world.com',
                'balance' => 100.0,
                'tags' => [
                    [
                        'id' => 1,
                        'user_id' => 1,
                        'tag_id' => 1,
                        'as' => 'primary',
                        '@' => [
                            'id' => 1,
                            'name' => 'tag a',
                        ],
                        'image' => [
                            'id' => 1,
                            'url' => 'first.jpg',
                            'parent_id' => 1,
                        ],
                    ],
                    [
                        'id' => 2,
                        'user_id' => 1,
                        'tag_id' => 2,
                        'as' => 'secondary',
                        '@' => [
                            'id' => 2,
                            'name' => 'tag b',
                        ],
                        'image' => null,
                    ],
                ],
            ],
            [
                'id' => 2,
                'email' => 'another@world.com',
                'balance' => 200.0,
                'tags' => [
                    [
                        'id' => 3,
                        'user_id' => 2,
                        'tag_id' => 3,
                        'as' => 'primary',
                        '@' => [
                            'id' => 3,
                            'name' => 'tag c',
                        ],
                        'image' => [
                            'id' => 2,
                            'url' => 'second.png',
                            'parent_id' => 3,
                        ],
                    ],
                ],
            ],
        ], $selector->fetchData(typecast: true));
    }

    public function testLoadRelationInload(): void
    {
        $selector = new Select($this->orm, User::class);
        $selector
            ->load('tags', ['method' => Select\AbstractLoader::INLOAD])
            ->load('tags.@.image')
            ->orderBy('id', 'ASC');

        $this->assertSame([
            [
                'id' => 1,
                'email' => 'hello@world.com',
                'balance' => 100.0,
                'tags' => [
                    [
                        'id' => 1,
                        'user_id' => 1,
                        'tag_id' => 1,
                        'as' => 'primary',
                        '@' => [
                            'id' => 1,
                            'name' => 'tag a',
                        ],
                        'image' => [
                            'id' => 1,
                            'url' => 'first.jpg',
                            'parent_id' => 1,
                        ],
                    ],
                    [
                        'id' => 2,
                        'user_id' => 1,
                        'tag_id' => 2,
                        'as' => 'secondary',
                        '@' => [
                            'id' => 2,
                            'name' => 'tag b',
                        ],
                        'image' => null,
                    ],
                ],
            ],
            [
                'id' => 2,
                'email' => 'another@world.com',
                'balance' => 200.0,
                'tags' => [
                    [
                        'id' => 3,
                        'user_id' => 2,
                        'tag_id' => 3,
                        'as' => 'primary',
                        '@' => [
                            'id' => 3,
                            'name' => 'tag c',
                        ],
                        'image' => [
                            'id' => 2,
                            'url' => 'second.png',
                            'parent_id' => 3,
                        ],
                    ],
                ],
            ],
        ], $selector->fetchData());
    }

    public function testLoadRelationPostload(): void
    {
        $selector = new Select($this->orm, User::class);
        $selector
            ->load('tags', ['method' => Select\AbstractLoader::INLOAD])
            ->load('tags.@.image', ['method' => Select\AbstractLoader::POSTLOAD])
            ->orderBy('id', 'ASC');

        $this->assertSame([
            [
                'id' => 1,
                'email' => 'hello@world.com',
                'balance' => 100.0,
                'tags' => [
                    [
                        'id' => 1,
                        'user_id' => 1,
                        'tag_id' => 1,
                        'as' => 'primary',
                        '@' => [
                            'id' => 1,
                            'name' => 'tag a',
                        ],
                        'image' => [
                            'id' => 1,
                            'url' => 'first.jpg',
                            'parent_id' => 1,
                        ],
                    ],
                    [
                        'id' => 2,
                        'user_id' => 1,
                        'tag_id' => 2,
                        'as' => 'secondary',
                        '@' => [
                            'id' => 2,
                            'name' => 'tag b',
                        ],
                        'image' => null,
                    ],
                ],
            ],
            [
                'id' => 2,
                'email' => 'another@world.com',
                'balance' => 200.0,
                'tags' => [
                    [
                        'id' => 3,
                        'user_id' => 2,
                        'tag_id' => 3,
                        'as' => 'primary',
                        '@' => [
                            'id' => 3,
                            'name' => 'tag c',
                        ],
                        'image' => [
                            'id' => 2,
                            'url' => 'second.png',
                            'parent_id' => 3,
                        ],
                    ],
                ],
            ],
        ], $selector->fetchData());
    }

    public function testAccessLoadedBranch(): void
    {
        $selector = new Select($this->orm, User::class);
        $selector->load('tags.@.image')->orderBy('id', 'ASC');

        /**
         * @var User $a
         * @var User $b
         */
        [$a, $b] = $selector->load('tags')->fetchAll();

        $this->assertCount(2, $a->tags);
        $this->assertCount(1, $b->tags);

        $this->assertInstanceOf(PivotedCollectionInterface::class, $a->tags);
        $this->assertInstanceOf(PivotedCollectionInterface::class, $b->tags);

        $this->assertTrue($a->tags->hasPivot($a->tags[0]));
        $this->assertTrue($a->tags->hasPivot($a->tags[1]));
        $this->assertTrue($b->tags->hasPivot($b->tags[0]));

        $this->assertFalse($b->tags->hasPivot($a->tags[0]));
        $this->assertFalse($b->tags->hasPivot($a->tags[1]));
        $this->assertFalse($a->tags->hasPivot($b->tags[0]));

        $this->assertInstanceOf(TagContext::class, $a->tags->getPivot($a->tags[0]));
        $this->assertInstanceOf(TagContext::class, $a->tags->getPivot($a->tags[1]));
        $this->assertInstanceOf(TagContext::class, $b->tags->getPivot($b->tags[0]));

        $this->assertEquals('primary', $a->tags->getPivot($a->tags[0])->as);
        $this->assertEquals('secondary', $a->tags->getPivot($a->tags[1])->as);
        $this->assertEquals('primary', $b->tags->getPivot($b->tags[0])->as);

        $this->assertEquals('first.jpg', $a->tags->getPivot($a->tags[0])->image->url);
        $this->assertNull($a->tags->getPivot($a->tags[1])->image);
        $this->assertEquals('second.png', $b->tags->getPivot($b->tags[0])->image->url);
    }

    public function testUpdateLoadedBranch(): void
    {
        $selector = new Select($this->orm, User::class);
        $selector->load('tags.@.image')->orderBy('id', 'ASC');

        /**
         * @var User $a
         * @var User $b
         */
        [$a, $b] = $selector->load('tags')->fetchAll();

        $this->assertCount(2, $a->tags);
        $this->assertCount(1, $b->tags);

        $this->assertInstanceOf(PivotedCollectionInterface::class, $a->tags);
        $this->assertInstanceOf(PivotedCollectionInterface::class, $b->tags);

        $b->tags->getPivot($b->tags[0])->image->url = 'new.gif';

        $this->captureWriteQueries();
        $tr = new Transaction($this->orm);
        $tr->persist($b);
        $tr->run();
        $this->assertNumWrites(1);

        $selector = new Select($this->orm->withHeap(new Heap()), User::class);
        $selector->load('tags.@.image')->orderBy('id', 'ASC');

        /**
         * @var User $a
         * @var User $b
         */
        [$a, $b] = $selector->load('tags')->fetchAll();
        $this->assertSame('new.gif', $b->tags->getPivot($b->tags[0])->image->url);
    }

    public function testFilterByPivotedBranch(): void
    {
        $selector = new Select($this->orm, User::class);
        $selector
            ->load('tags.@.image')
            ->where('tags.@.image.url', 'second.png')
            ->orderBy('id', 'ASC');

        $this->assertSame([
            [
                'id' => 2,
                'email' => 'another@world.com',
                'balance' => 200.0,
                'tags' => [
                    [
                        'id' => 3,
                        'user_id' => 2,
                        'tag_id' => 3,
                        'as' => 'primary',
                        '@' => [
                            'id' => 3,
                            'name' => 'tag c',
                        ],
                        'image' => [
                            'id' => 2,
                            'url' => 'second.png',
                            'parent_id' => 3,
                        ],
                    ],
                ],
            ],
        ], $selector->fetchData());
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTable('user', [
            'id' => 'primary',
            'email' => 'string',
            'balance' => 'float',
        ]);

        $this->makeTable('tag', [
            'id' => 'primary',
            'name' => 'string',
        ]);

        $this->makeTable('tag_user_map', [
            'id' => 'primary',
            'user_id' => 'integer',
            'tag_id' => 'integer',
            'as' => 'string,nullable',
        ]);

        $this->makeTable('images', [
            'id' => 'primary',
            'parent_id' => 'integer',
            'url' => 'string',
        ]);

        $this->makeFK('tag_user_map', 'user_id', 'user', 'id');
        $this->makeFK('tag_user_map', 'tag_id', 'tag', 'id');

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

        $this->getDatabase()->table('images')->insertMultiple(
            ['parent_id', 'url'],
            [
                [1, 'first.jpg'],
                [3, 'second.png'],
            ],
        );

        $this->orm = $this->withSchema(new Schema($this->getSchemaArray()));
    }

    private function getSchemaArray(): array
    {
        return [
            User::class => [
                SchemaInterface::ROLE => 'user',
                SchemaInterface::MAPPER => Mapper::class,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'user',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'email', 'balance'],
                SchemaInterface::TYPECAST => [
                    'id' => 'int',
                    'balance' => 'float',
                ],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [
                    'tags' => [
                        Relation::TYPE => Relation::MANY_TO_MANY,
                        Relation::TARGET => Tag::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::THROUGH_ENTITY => TagContext::class,
                            Relation::INNER_KEY => 'id',
                            Relation::OUTER_KEY => 'id',
                            Relation::THROUGH_INNER_KEY => 'user_id',
                            Relation::THROUGH_OUTER_KEY => 'tag_id',
                        ],
                    ],
                ],
            ],
            Tag::class => [
                SchemaInterface::ROLE => 'tag',
                SchemaInterface::MAPPER => Mapper::class,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'tag',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'name'],
                SchemaInterface::TYPECAST => ['id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
                SchemaInterface::SCOPE => SortByIDScope::class,
            ],
            TagContext::class => [
                SchemaInterface::ROLE => 'tag_context',
                SchemaInterface::MAPPER => Mapper::class,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'tag_user_map',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'user_id', 'tag_id', 'as'],
                SchemaInterface::TYPECAST => ['id' => 'int', 'user_id' => 'int', 'tag_id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [
                    'image' => [
                        Relation::TYPE => Relation::HAS_ONE,
                        Relation::TARGET => Image::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::INNER_KEY => 'id',
                            Relation::OUTER_KEY => 'parent_id',
                        ],
                    ],
                ],
            ],
            Image::class => [
                SchemaInterface::ROLE => 'image',
                SchemaInterface::MAPPER => Mapper::class,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'images',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'url', 'parent_id'],
                SchemaInterface::TYPECAST => ['id' => 'int', 'parent_id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
            ],
        ];
    }
}
