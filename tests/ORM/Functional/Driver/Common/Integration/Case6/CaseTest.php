<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Integration\Case6;

use Cycle\ORM\Select;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Functional\Driver\Common\Integration\Case6\Entity\User;
use Cycle\ORM\Tests\Functional\Driver\Common\Integration\IntegrationTestTrait;
use Cycle\ORM\Tests\Traits\TableTrait;

abstract class CaseTest extends BaseTest
{
    use IntegrationTestTrait;
    use TableTrait;

    public function setUp(): void
    {
        // Init DB
        parent::setUp();

        // Make tables
        $this->makeTable('users', [
            'id' => 'int,primary',
            'login' => 'string',
        ]);

        $this->loadSchema(__DIR__ . '/schema.php');

        $this->getDatabase()->table('users')->insertMultiple(
            ['id', 'login'],
            [
                [1, 'foo'],
            ],
        );
    }

    public function testSelect(): void
    {
        /** @var User $model */
        $model = (new Select($this->orm,User::class))
            ->wherePK(1)
            ->fetchOne();

        $this->assertSame('foo', $model->getLogin());
        $this->expectException(\Exception::class);
        $model->login = 'new login';
    }
}
