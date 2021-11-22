<?php

declare(strict_types=1);

namespace Cycle\ORM\Heap;

use Cycle\ORM\Context\ConsumerInterface;
use Cycle\ORM\Heap\Traits\RelationTrait;
use Cycle\ORM\Reference\ReferenceInterface;
use Cycle\ORM\RelationMap;
use DateTimeImmutable;
use DateTimeInterface;
use JetBrains\PhpStorm\ExpectedValues;
use Stringable;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;
use const SORT_STRING;

/**
 * Node (metadata) carries meta information about entity state, changes forwards data to other points through
 * inner states.
 */
final class Node implements ConsumerInterface
{
    use RelationTrait;

    // Different entity states in a pool
    public const NEW = 1;
    public const MANAGED = 2;
    public const SCHEDULED_INSERT = 3;
    public const SCHEDULED_UPDATE = 4;
    public const SCHEDULED_DELETE = 5;
    public const DELETED = 6;

    private ?State $state = null;
    private array $rawData = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        #[ExpectedValues(valuesFromClass: self::class)]
        private int $status,
        private array $data,
        private string $role
    ) {
        $this->updateRawData();
    }

    private function updateRawData(): void
    {
        $this->rawData = [];
        foreach ($this->data as $field => $value) {
            if (!\is_object($value)) {
                continue;
            }
            $this->rawData[$field] = self::convertToSolid($value);
        }
    }

    /**
     * Reset state.
     */
    public function __destruct()
    {
        unset($this->data, $this->rawData, $this->state, $this->relations);
    }

    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Current point state (set of changes).
     */
    public function getState(): State
    {
        if ($this->state === null) {
            $this->state = new State($this->status, $this->data, $this->rawData);
        }

        return $this->state;
    }

    public function hasState(): bool
    {
        return $this->state !== null;
    }

    /**
     * Set new state value.
     */
    public function setStatus(int $state): void
    {
        $this->getState()->setStatus($state);
    }

    /**
     * Get current state.
     */
    public function getStatus(): int
    {
        return $this->state?->getStatus() ?? $this->status;
    }

    /**
     * Set new state data (will trigger state handlers).
     */
    public function setData(array $data): void
    {
        $this->getState()->setData($data);
    }

    /**
     * Get current state data. Mutalbe inside the transaction.
     */
    public function getData(): array
    {
        return $this->state?->getData() ?? $this->data;
    }

    /**
     * The intial (post-load) node date. Does not change during the transaction.
     */
    public function getInitialData(): array
    {
        return $this->data;
    }

    public function register(string $key, mixed $value): void
    {
        $this->getState()->register($key, $value);
    }

    /**
     * Sync the point state and return data diff.
     */
    public function syncState(RelationMap $relMap): array
    {
        if ($this->state === null) {
            return [];
        }

        $changes = array_udiff_assoc($this->state->getTransactionData(), $this->data, [self::class, 'compare']);

        $relations = $relMap->getRelations();
        foreach ($this->state->getRelations() as $name => $relation) {
            if ($relation instanceof ReferenceInterface
                && isset($relations[$name])
                && (isset($this->relations[$name]) xor isset($relation))
            ) {
                $changes[$name] = $relation->hasValue() ? $relation->getValue() : $relation;
            }
            $this->setRelation($name, $relation);
        }

        // DELETE handled separately
        $this->status = self::MANAGED;
        $this->data = $this->state->getTransactionData();
        $this->updateRawData();
        $this->state->__destruct();
        $this->state = null;
        $this->relationStatus = [];

        return $changes;
    }

    public function hasChanges(): bool
    {
        return ($this->state !== null && $this->state->getStatus() === self::NEW)
            || $this->state->getChanges() !== [];
    }

    public function getChanges(): array
    {
        if ($this->state === null) {
            return $this->status === self::NEW ? $this->data : [];
        }
        return $this->state->getChanges();
    }

    /**
     * Reset point state and flush all the changes.
     */
    public function resetState(): void
    {
        if (isset($this->state)) {
            $this->state->__destruct();
        }
        $this->state = null;
        $this->relationStatus = [];
    }

    /**
     * @internal
     */
    public static function convertToSolid(mixed $value): mixed
    {
        if (!\is_object($value)) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value instanceof DateTimeImmutable ? $value : DateTimeImmutable::createFromInterface($value);
        }
        return $value instanceof \Stringable ? $value->__toString() : $value;
    }

    public static function compare(mixed $a, mixed $b): int
    {
        if ($a === $b) {
            return 0;
        }
        if ($a === null xor $b === null) {
            return 1;
        }

        $ta = [\gettype($a), \gettype($b)];

        // array, boolean, double, integer, object, string
        \sort($ta, SORT_STRING);

        if ($ta[0] === 'object' || $ta[1] === 'object') {
            // Both are objects
            if ($ta[0] === $ta[1]) {
                if ($a instanceof DateTimeInterface && $b instanceof DateTimeInterface) {
                    return $a <=> $b;
                }
                if ($a instanceof \Stringable && $b instanceof \Stringable) {
                    return $a->__toString() <=> $b->__toString();
                }
                return (int)($a::class !== $b::class || (array)$a !== (array)$b);
            }
            // Object and string/int
            if ($ta[1] === 'string' || $ta[0] === 'integer') {
                $a = $a instanceof Stringable ? $a->__toString() : (string)$a;
                $b = $b instanceof Stringable ? $b->__toString() : (string)$b;
                return $a <=> $b;
            }
            return -1;
        }

        if ($ta[1] === 'string') {
            if ($a === '' || $b === '') {
                return -1;
            }
            if ($ta[0] === 'integer') {
                return \is_numeric($a) && \is_numeric($b) ? (int)((string)$a !== (string)$b) : -1;
            }
            if ($ta[0] === 'double') {
                return \is_numeric($a) && \is_numeric($b) ? (int)((float)$a !== (float)$b) : -1;
            }
        }

        if ($ta[0] === 'boolean') {
            $a = \filter_var($a, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $b = \filter_var($b, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return (int)($a !== $b);
        }

        if ($ta === ['double', 'integer']) {
            return (int)((float)$a !== (float)$b);
        }

        return 1;
    }
}
