<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Benchmark;

use Cycle\ORM\Heap\Heap;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Relation;
use Cycle\ORM\Schema;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Fixtures\Cyclic;
use Cycle\ORM\Tests\Traits\TableTrait;
use Cycle\ORM\Tests\Util\DontGenerateAttribute;
use Cycle\ORM\Transaction;

#[DontGenerateAttribute]
abstract class BenchmarkDoubleLinkedTest extends BaseTest
{
    use TableTrait;

    public function testClean(): void
    {
        $this->orm = $this->orm->withHeap(new Heap());
        $tr = new Transaction($this->orm);

        for ($i = 0; $i < 10000; $i++) {
            // inverted
            $c1 = new Cyclic();
            $c1->name = 'clean';

            $tr->persist($c1, 1);
        }

        $tr->run();
    }

    public function testMemoryUsage(): void
    {
        $this->orm = $this->orm->withHeap(new Heap());
        $tr = new Transaction($this->orm);

        for ($i = 0; $i < 10000; $i++) {
            // inverted
            $c1 = new Cyclic();
            $c1->name = 'self-reference';
            $c1->cyclic = $c1;

            $tr->persist($c1);
        }

        $tr->run();
    }

    public function testMemoryUsageOther(): void
    {
        $this->orm = $this->orm->withHeap(new Heap());
        $tr = new Transaction($this->orm);

        for ($i = 0; $i < 10000; $i++) {
            // inverted
            $c1 = new Cyclic();
            $c1->name = 'self-reference';
            $c1->other = $c1;

            $tr->persist($c1);
        }

        $tr->run();
    }

    public function testMemoryUsageDouble(): void
    {
        $this->orm = $this->orm->withHeap(new Heap());
        $tr = new Transaction($this->orm);

        for ($i = 0; $i < 10000; $i++) {
            // inverted
            $c1 = new Cyclic();
            $c1->name = 'self-reference';
            $c1->cyclic = $c1;
            $c1->other = $c1;

            $tr->persist($c1);
        }

        $tr->run();
    }

    public function setUp(): void
    {
        if (!BaseTest::$config['benchmark']) {
            $this->markTestSkipped();
            return;
        }

        parent::setUp();

        $this->makeTable('cyclic', [
            'id' => 'primary',
            'name' => 'string',
            'parent_id' => 'integer,nullable',
        ]);

        $this->orm = $this->withSchema(new Schema([
            Cyclic::class => [
                Schema::ROLE => 'cyclic',
                Schema::MAPPER => Mapper::class,
                Schema::DATABASE => 'default',
                Schema::TABLE => 'cyclic',
                Schema::PRIMARY_KEY => 'id',
                Schema::FIND_BY_KEYS => ['parent_id'],
                Schema::COLUMNS => ['id', 'parent_id', 'name'],
                Schema::SCHEMA => [],
                Schema::RELATIONS => [
                    'cyclic' => [
                        Relation::TYPE => Relation::HAS_ONE,
                        Relation::TARGET => Cyclic::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::NULLABLE => true,
                            Relation::INNER_KEY => 'id',
                            Relation::OUTER_KEY => 'parent_id',
                        ],
                    ],
                    'other' => [
                        Relation::TYPE => Relation::REFERS_TO,
                        Relation::TARGET => Cyclic::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::NULLABLE => true,
                            Relation::INNER_KEY => 'parent_id',
                            Relation::OUTER_KEY => 'id',
                        ],
                    ],
                ],
            ],
        ]));
    }
}
