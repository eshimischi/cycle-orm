<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\JTI\Relation;

use Cycle\ORM\Relation;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Select;
use Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\Fixture\Book;
use Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\Fixture\Employee;
use Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\Fixture\Engineer;
use Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\Fixture\Manager;
use Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\Fixture\Programator;
use Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\Fixture\Tool;
use Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\JTI\JtiBaseTest;
use Cycle\ORM\Tests\Functional\Driver\Common\Inheritance\JTI\SimpleCasesTest;
use Cycle\ORM\Transaction;

abstract class ParentClassRelationsTest extends SimpleCasesTest
{
    protected const TOOL_1 = ['id' => 1, 'engineer_id' => 2, 'title' => 'Hammer'];
    protected const TOOL_2 = ['id' => 2, 'engineer_id' => 2, 'title' => 'Notebook'];
    protected const TOOL_3 = ['id' => 3, 'engineer_id' => 2, 'title' => 'Notepad'];
    protected const TOOL_4 = ['id' => 4, 'engineer_id' => 2, 'title' => 'IDE'];
    protected const BOOK_1 = ['id' => 1, 'title' => 'PHP manual'];
    protected const BOOK_2 = ['id' => 2, 'title' => 'Best mentor'];
    protected const BOOK_3 = ['id' => 3, 'title' => 'Wikipedia vol.42'];
    protected const BOOK_4 = ['id' => 4, 'title' => 'How to be Foo when you are Bar'];
    protected const EMPLOYEE_1 = parent::EMPLOYEE_1 + ['book_id' => 3];
    protected const EMPLOYEE_2 = parent::EMPLOYEE_2 + ['book_id' => 2];
    protected const EMPLOYEE_3 = parent::EMPLOYEE_3 + ['book_id' => 1];
    protected const EMPLOYEE_4 = parent::EMPLOYEE_4 + ['book_id' => null];
    protected const ENGINEER_2 = parent::ENGINEER_2 + ['tech_book_id' => 1];
    protected const ENGINEER_4 = parent::ENGINEER_4 + ['tech_book_id' => 4];
    protected const EMPLOYEE_1_LOADED = self::EMPLOYEE_1 + ['book' => self::BOOK_3];
    protected const EMPLOYEE_2_LOADED = self::EMPLOYEE_2 + ['book' => self::BOOK_2];
    protected const EMPLOYEE_3_LOADED = self::EMPLOYEE_3 + ['book' => self::BOOK_1];
    protected const EMPLOYEE_4_LOADED = self::EMPLOYEE_4 + ['book' => null];
    protected const ENGINEER_2_LOADED = self::ENGINEER_2 + self::EMPLOYEE_2_LOADED;
    protected const ENGINEER_4_LOADED = self::ENGINEER_4 + self::EMPLOYEE_4_LOADED;
    protected const PROGRAMATOR_2_LOADED = self::PROGRAMATOR_2 + self::ENGINEER_2_LOADED;
    protected const PROGRAMATOR_4_LOADED = self::PROGRAMATOR_4 + self::ENGINEER_4_LOADED;
    protected const MANAGER_1_LOADED = self::MANAGER_1 + self::EMPLOYEE_1_LOADED;
    protected const MANAGER_3_LOADED = self::MANAGER_3 + self::EMPLOYEE_3_LOADED;
    protected const EMPLOYEE_ALL_LOADED = [
        self::EMPLOYEE_1_LOADED,
        self::EMPLOYEE_2_LOADED,
        self::EMPLOYEE_3_LOADED,
        self::EMPLOYEE_4_LOADED,
    ];
    protected const EMPLOYEE_INHERITED_LOADED = [
        self::MANAGER_1_LOADED,
        self::PROGRAMATOR_2_LOADED,
        self::MANAGER_3_LOADED,
        self::PROGRAMATOR_4_LOADED,
    ];
    protected const ENGINEER_ALL_LOADED = [self::ENGINEER_2_LOADED, self::ENGINEER_4_LOADED];
    protected const PROGRAMATOR_ALL_LOADED = [self::PROGRAMATOR_2_LOADED, self::PROGRAMATOR_4_LOADED];
    protected const MANAGER_ALL_LOADED = [self::MANAGER_1_LOADED, self::MANAGER_3_LOADED];
    protected const TOOL_ROLE = 'tool';
    protected const BOOK_ROLE = 'book';

    /**
     * Parent's relation should be initialized
     */
    public function testLoadParentRelations(): void
    {
        /** @var Programator $entity */
        $entity = (new Select($this->orm, static::PROGRAMATOR_ROLE))
            ->load('tech_book')
            ->wherePK(2)->fetchOne();

        $this->assertNotNull($entity->book);
        $this->assertNotNull($entity->tech_book);
    }

    /**
     * Parent's relations should be removed or not removed with their parent
     */
    public function testRemoveSubclassWithRelations(): void
    {
        /** @var Engineer $engineer */
        $engineer = (new Select($this->orm, static::ENGINEER_ROLE))
            ->loadSubclasses(false)
            ->wherePK(2)->fetchOne();

        $this->captureWriteQueries();
        (new Transaction($this->orm))->delete($engineer)->run();
        $this->assertNumWrites(1);

        $this->captureWriteQueries();
        (new Transaction($this->orm))->delete($engineer)->run();
        $this->assertNumWrites(0);

        $this->assertNull(
            (new Select($this->orm, static::PROGRAMATOR_ROLE))
                ->wherePK(2)
                ->fetchOne(),
        );
        $this->assertNull(
            (new Select($this->orm, static::ENGINEER_ROLE))
                ->loadSubclasses(false)
                ->wherePK(2)
                ->fetchOne(),
        );
        /** @var Employee $employee */
        $employee = (new Select($this->orm, static::EMPLOYEE_ROLE))
            ->loadSubclasses(false)
            ->wherePK(2)
            ->fetchOne();

        $this->assertNotNull($employee);
        $this->assertNotNull($employee->book);
    }

    public function setUp(): void
    {
        JtiBaseTest::setUp();

        $this->makeTable('book', [
            'id' => 'integer',
            'title' => 'string',
        ], pk: ['id']);
        $this->makeTable('tool', [
            'id' => 'integer, primary',
            'engineer_id' => 'integer',
            'title' => 'string',
        ], pk: ['id']);
        $this->makeTable('employee', [
            'id' => 'integer',
            'name' => 'string',
            'age' => 'integer,nullable',
            'book_id' => 'integer,nullable',
        ], fk: [
            'book_id' => ['table' => 'book', 'column' => 'id'],
        ], pk: ['id']);
        $this->makeTable('engineer', [
            'id' => 'integer',
            'level' => 'integer',
            'tech_book_id' => 'integer,nullable',
        ], fk: [
            'id' => ['table' => 'employee', 'column' => 'id'],
        ], pk: ['id']);
        $this->makeTable('programator', [
            'id' => 'integer',
            'language' => 'string',
        ], fk: [
            'id' => ['table' => 'engineer', 'column' => 'id'],
        ], pk: ['id']);
        $this->makeTable('manager', [
            'id' => 'integer',
            'rank' => 'string',
        ], fk: [
            'id' => ['table' => 'employee', 'column' => 'id'],
        ], pk: ['id']);

        $this->getDatabase()->table('tool')->insertMultiple(
            array_keys(static::TOOL_1),
            [
                self::TOOL_1,
                self::TOOL_2,
                self::TOOL_3,
                self::TOOL_4,
            ],
        );
        $this->getDatabase()->table('book')->insertMultiple(
            array_keys(static::BOOK_1),
            [
                self::BOOK_1,
                self::BOOK_2,
                self::BOOK_3,
                self::BOOK_4,
            ],
        );
        $this->getDatabase()->table('employee')->insertMultiple(
            array_keys(static::EMPLOYEE_1),
            [
                self::EMPLOYEE_1,
                self::EMPLOYEE_2,
                self::EMPLOYEE_3,
                self::EMPLOYEE_4,
            ],
        );
        $this->getDatabase()->table('engineer')->insertMultiple(
            array_keys(static::ENGINEER_2),
            [
                self::ENGINEER_2,
                self::ENGINEER_4,
            ],
        );
        $this->getDatabase()->table('programator')->insertMultiple(
            array_keys(static::PROGRAMATOR_2),
            [
                self::PROGRAMATOR_2,
                self::PROGRAMATOR_4,
            ],
        );
        $this->getDatabase()->table('manager')->insertMultiple(
            array_keys(static::MANAGER_1),
            [
                self::MANAGER_1,
                self::MANAGER_3,
            ],
        );
    }

    protected function getSchemaArray(): array
    {
        return [
            static::EMPLOYEE_ROLE => [
                SchemaInterface::ENTITY => Employee::class,
                SchemaInterface::MAPPER => static::DEFAULT_MAPPER,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'employee',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'name', 'age', 'book_id'],
                SchemaInterface::TYPECAST => ['id' => 'int', 'book_id' => 'int', 'age' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [
                    'book' => [
                        Relation::TYPE => Relation::REFERS_TO,
                        Relation::TARGET => static::BOOK_ROLE,
                        Relation::LOAD => Relation::LOAD_EAGER,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::NULLABLE => true,
                            Relation::INNER_KEY => 'book_id',
                            Relation::OUTER_KEY => 'id',
                        ],
                    ],
                ],
            ],
            static::ENGINEER_ROLE => [
                SchemaInterface::ENTITY => Engineer::class,
                SchemaInterface::MAPPER => static::DEFAULT_MAPPER,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'engineer',
                SchemaInterface::PARENT => static::EMPLOYEE_ROLE,
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'level', 'tech_book_id'],
                SchemaInterface::TYPECAST => ['id' => 'int', 'level' => 'int', 'tech_book_id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [
                    'tech_book' => [
                        Relation::TYPE => Relation::REFERS_TO,
                        Relation::TARGET => static::BOOK_ROLE,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::NULLABLE => true,
                            Relation::INNER_KEY => 'tech_book_id',
                            Relation::OUTER_KEY => 'id',
                        ],
                    ],
                    'tools' => [
                        Relation::TYPE => Relation::HAS_MANY,
                        Relation::TARGET => static::TOOL_ROLE,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::NULLABLE => false,
                            Relation::INNER_KEY => 'id',
                            Relation::OUTER_KEY => 'engineer_id',
                        ],
                    ],
                ],
            ],
            static::PROGRAMATOR_ROLE => [
                SchemaInterface::ENTITY => Programator::class,
                SchemaInterface::MAPPER => static::DEFAULT_MAPPER,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'programator',
                SchemaInterface::PARENT => static::ENGINEER_ROLE,
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'language'],
                SchemaInterface::TYPECAST => ['id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
            ],
            static::MANAGER_ROLE => [
                SchemaInterface::ENTITY => Manager::class,
                SchemaInterface::MAPPER => static::DEFAULT_MAPPER,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'manager',
                SchemaInterface::PARENT => static::EMPLOYEE_ROLE,
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'rank'],
                SchemaInterface::TYPECAST => ['id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
            ],
            static::BOOK_ROLE => [
                SchemaInterface::ENTITY => Book::class,
                SchemaInterface::MAPPER => static::DEFAULT_MAPPER,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'book',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'title'],
                SchemaInterface::TYPECAST => ['id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
            ],
            static::TOOL_ROLE => [
                SchemaInterface::ENTITY => Tool::class,
                SchemaInterface::MAPPER => static::DEFAULT_MAPPER,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'tool',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'title', 'engineer_id'],
                SchemaInterface::TYPECAST => ['id' => 'int', 'engineer_id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
            ],
        ];
    }
}
