<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Mapper\ProxyEntityMapper;

use Cycle\ORM\Exception\MapperException;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Tests\Functional\Driver\Common\Mapper\BaseMapperTest;

class EntityHydrationTest extends BaseMapperTest
{
    public const DRIVER = 'sqlite';

    public function testDataForBaseClassShouldBeExtracted(): void
    {
        $user = new EntityHydrationUser(123, 'guest', 'guest@site.com');

        $mapper = $this->orm->getMapper($user);

        $this->assertEquals([
            'id' => 123,
            'username' => 'guest',
            'email' => 'guest@site.com',
            'attributes' => [],
        ], $mapper->extract($user));
    }

    public function testDataForExtendedClassShouldBeExtracted(): void
    {
        $user = new ExtendedEntityHydrationUser(123, 'guest', 'guest@site.com', true, 234);

        $mapper = $this->orm->getMapper($user);

        $this->assertEquals([
            'id' => 123,
            'username' => 'guest',
            'email' => 'guest@site.com',
            'isVerified' => true,
            'profileId' => 234,
            'attributes' => [],
        ], $mapper->extract($user));
    }

    public function testDataShouldBeHydratedToBaseClass(): void
    {
        $mapper = $this->orm->getMapper(EntityHydrationUser::class);

        $emptyObject = $mapper->init([]);
        $this->assertInstanceOf(EntityHydrationUser::class, $emptyObject);

        $user = $mapper->hydrate($emptyObject, [
            'id' => 123,
            'username' => 'guest',
            'email' => 'guest@site.com',
        ]);

        $this->assertSame(123, $user->getId());
        $this->assertSame('guest', $user->getUsername());
        $this->assertSame('guest@site.com', $user->getEmail());
    }

    public function testDataShouldBeHydratedToExtendedClass(): void
    {
        $mapper = $this->orm->getMapper(ExtendedEntityHydrationUser::class);

        $emptyObject = $mapper->init([]);
        $this->assertInstanceOf(ExtendedEntityHydrationUser::class, $emptyObject);

        $user = $mapper->hydrate($emptyObject, [
            'id' => 123,
            'username' => 'guest',
            'email' => 'guest@site.com',
            'isVerified' => true,
            'profileId' => 234,
        ]);

        $this->assertSame(123, $user->getId());
        $this->assertSame('guest', $user->getUsername());
        $this->assertSame('guest@site.com', $user->getEmail());
        $this->assertSame(true, $user->isVerified());
        $this->assertSame(234, $user->getProfileId());
    }

    public function testUndefinedPropertiesShouldBePassedThroughSetter(): void
    {
        $mapper = $this->orm->getMapper(EntityHydrationUser::class);

        $emptyObject = $mapper->init([]);

        $user = $mapper->hydrate($emptyObject, [
            'id' => 123,
            'tag' => 'test',
            'username' => 'guest',
            'email' => 'guest@site.com',
        ]);

        $this->assertEquals(['tag' => 'test'], $user->getAttributes());
    }

    public function testRequestedUndefinedPropertiesShouldBePassedThroughGetter(): void
    {
        $mapper = $this->orm->getMapper(EntityHydrationUser::class);

        $emptyObject = $mapper->init([]);

        $user = $mapper->hydrate($emptyObject, [
            'id' => 123,
            'tag' => 'test',
            'username' => 'guest',
            'email' => 'guest@site.com',
        ]);

        $this->assertEquals('test', $user->tag);
    }

    public function testWriteDynamicProperty(): void
    {
        $mapper = $this->orm->getMapper(EntityHydrationIdentityUser::class);
        $emptyObject = new EntityHydrationIdentityUser();

        $mapper->hydrate($emptyObject, ['id' => 123, 'value' => 'test']);

        // self::assertSame(['id' => 123], $mapper->fetchFields($emptyObject));
        self::assertTrue(true, 'No exception thrown');
    }

    public function testHydrateBadType(): void
    {
        $mapper = $this->orm->getMapper(EntityHydrationIdentityUser::class);
        $emptyObject = new EntityHydrationIdentityUser();

        $this->expectException(MapperException::class);
        $this->expectExceptionMessage("Can't hydrate an entity because property and value types are incompatible.");

        try {
            $mapper->hydrate($emptyObject, ['id' => 'string value']);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\TypeError::class, $e->getPrevious());
            throw $e;
        }
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->orm = $this->withSchema(
            new Schema(
                [
                    EntityHydrationUser::class => [
                        SchemaInterface::MAPPER => Mapper::class,
                        SchemaInterface::DATABASE => 'default',
                        SchemaInterface::TABLE => 'user',
                        SchemaInterface::PRIMARY_KEY => 'uuid',
                        SchemaInterface::COLUMNS => ['id', 'username', 'email'],
                        SchemaInterface::TYPECAST => [],
                        SchemaInterface::SCHEMA => [],
                        SchemaInterface::RELATIONS => [],
                    ],
                    ExtendedEntityHydrationUser::class => [
                        SchemaInterface::MAPPER => Mapper::class,
                        SchemaInterface::DATABASE => 'default',
                        SchemaInterface::TABLE => 'user',
                        SchemaInterface::PRIMARY_KEY => 'uuid',
                        SchemaInterface::COLUMNS => ['id', 'username', 'email', 'isVerified', 'profileId'],
                        SchemaInterface::TYPECAST => [],
                        SchemaInterface::SCHEMA => [],
                        SchemaInterface::RELATIONS => [],
                    ],
                    EntityHydrationIdentityUser::class => [
                        SchemaInterface::MAPPER => Mapper::class,
                        SchemaInterface::DATABASE => 'default',
                        SchemaInterface::TABLE => 'user',
                        SchemaInterface::PRIMARY_KEY => 'id',
                        SchemaInterface::COLUMNS => ['id', 'value'],
                        SchemaInterface::TYPECAST => [],
                        SchemaInterface::SCHEMA => [],
                        SchemaInterface::RELATIONS => [],
                    ],
                ],
            ),
        );
    }
}
// phpcs:disable
class EntityHydrationIdentityUser
{
    public int $id;
}

class EntityHydrationUser
{
    public int $id;
    protected string $username;
    private string $email;
    private array $attributes = [];

    public function __construct(int $id, string $username, string $email)
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __get(string $name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }
    }
}


class ExtendedEntityHydrationUser extends EntityHydrationUser
{
    protected bool $isVerified;
    private int $profileId;

    public function __construct(int $id, string $username, string $email, bool $isVerified, int $profileId)
    {
        parent::__construct($id, $username, $email);

        $this->isVerified = $isVerified;
        $this->profileId = $profileId;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function getProfileId(): int
    {
        return $this->profileId;
    }
}
// phpcs:enable
