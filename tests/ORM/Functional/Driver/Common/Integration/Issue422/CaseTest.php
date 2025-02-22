<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Integration\Issue422;

use Cycle\ORM\Select;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Functional\Driver\Common\Integration\IntegrationTestTrait;
use Cycle\ORM\Tests\Functional\Driver\Common\Integration\Issue422\Entity\Billing;
use Cycle\ORM\Tests\Functional\Driver\Common\Integration\Issue422\Entity\User;
use Cycle\ORM\Tests\Traits\TableTrait;

abstract class CaseTest extends BaseTest
{
    use IntegrationTestTrait;
    use TableTrait;

    public function testSelect(): void
    {
        /** @var User $user */
        $user = (new Select($this->orm, Entity\User::class))
            ->load('billing')
            ->wherePK(1)
            ->fetchOne();
        $this->assertInstanceOf(Billing::class, $user->billing);

        /** @var User $user */
        $user = (new Select($this->orm, Entity\User::class))
            ->load('billing')
            ->wherePK(2)
            ->fetchOne();
        $this->assertNull($user->billing);
        $this->assertSame(200, $user->otherEmbedded->propertyInt);
    }

    public function setUp(): void
    {
        // Init DB
        parent::setUp();
        $this->makeTables();
        $this->fillData();

        $this->loadSchema(__DIR__ . '/schema.php');
    }

    private function makeTables(): void
    {
        // Make tables
        $this->makeTable('user', [
            'id' => 'primary',
            'name' => 'string',
            'property_string' => 'string',
            'property_int' => 'int',
        ]);

        $this->makeTable('billing', [
            'id' => 'primary',
            'user_id' => 'int',
            'property_string' => 'string',
            'property_int' => 'int',
        ]);
        $this->makeFK('billing', 'user_id', 'user', 'id', 'NO ACTION', 'NO ACTION');
    }

    private function fillData(): void
    {
        $this->getDatabase()->table('user')->insertMultiple(
            ['name', 'property_string', 'property_int'],
            [
                ['user-with-billing', 'foo', 100],
                ['user-without-billing', 'bar', 200],
            ],
        );
        $this->getDatabase()->table('billing')->insertMultiple(
            ['user_id', 'property_string', 'property_int'],
            [
                [1, 'foo', 100],
            ],
        );
    }
}
