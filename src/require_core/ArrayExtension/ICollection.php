<?php

namespace require_core\ArrayExtension;

use ArrayAccess;
use Countable;
use Traversable;
use IteratorAggregate;
use JsonSerializable;

interface ICollection extends IteratorAggregate, ArrayAccess, JsonSerializable, Countable
{
    // custom
    function toArray(): array;
    function remove(callable $callback): bool;

    function first(callable $callback);

    function filter(callable $callback): ICollection;
    function map(callable $callback);
    function reduce(callable $callback, $initialValue = null);

    // function max(callable $callback);
    // function min(callable $callback);
    // function avg(callable $callback);
    function countBy(callable $callback): int;
    function count(): int;


    function add($value): void;

    // IteratorAggregate
    function getIterator(): Traversable;

    // ArrayAccess
    function offsetSet($offset, $value): void;
    function offsetExists($offset): bool;
    function offsetUnset($offset): void;
    function offsetGet($offset);

    // JsonSerializable
    function jsonSerialize(): array;
}
