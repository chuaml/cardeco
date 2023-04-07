<?php

namespace require_core\ArrayExtension;

use Generator;
use Traversable;

class LazyCollection implements ICollection
{

    private array $list;
    private array $callbacks = [];

    public function __construct(array $initalArray = [])
    {
        $this->list = $initalArray;
    }

    public function getList(): Generator
    {
        $list = $this->list;
        $pendingCallbacks = $this->callbacks;
        if (isset($this->callbacks[0]) === true) {
            foreach ($list as $key => $value) {
                $i = -1;
                while (isset($pendingCallbacks[++$i]) === true) {
                    // filter | map
                    $result = $pendingCallbacks[$i]->evaluateValue($key, $value);
                    if ($result === null) {
                        break;
                    } else {
                        yield $result;
                    }
                }
            }
        } else {
            foreach ($list as $v) {
                yield $v;
            }
        }
        return;
    }

    public function toArray(): array
    {
        // return [...$this->list];

        return [...$this->getList()];
    }

    public function remove(callable $callback): bool
    {
        foreach ($this->list as $key => $value) {
            if (call_user_func($callback, $value, $key)) {
                unset($this->list[$key]);
                return true;
            }
        }

        return false;
    }

    public function add($value): void
    {
        $this->list[] = $value;
    }

    public function first(callable $callback)
    {
        foreach ($this->list as $key => $value) {
            if (call_user_func($callback, $value, $key) === true) {
                return $value;
            }
        }

        return null;
    }

    public function filter(callable $callback): ICollection
    {
        // $buket = [];
        // foreach ($this->list as $key => $value) {
        //     if (call_user_func($callback, $value, $key) === true) {
        //         $buket[] = $value;
        //     }
        // }

        $this->callbacks[] = new _Collection_Callback_Filter($callback);

        return $this;
    }

    public function map(callable $callback): ICollection
    {
        // $result = array_map($callback, $this->list);
        // return new self($result);

        $this->callbacks[] = new _Collection_Callback_Map($callback);
        return $this;
    }

    public function reduce(callable $callback, $initialValue = null)
    {
        $list = $this->toArray();
        return array_reduce($list, $callback, $initialValue);
    }

    public function countBy(callable $callback): int
    {
        $total = 0;
        foreach ($this->getList() as $key => $value) {
            if (call_user_func($callback, $value, $key) === true) {
                ++$total;
            }
        }

        return $total;
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->getList() as $v) {
            ++$total;
        }
        return $total;
    }

    public function getIterator(): Traversable
    {
        return $this->getList();
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset) === true) {
            $this->list[] = $value;
        } else {
            $this->list[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->list[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->list[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->list[$offset]) ? $this->list[$offset] : null;
    }


    public function jsonSerialize(): array
    {
        return [...$this->getList()];
    }
}


interface _Collection_Callback
{
    function evaluateValue($key, $value);
}

class _Collection_Callback_Filter implements _Collection_Callback
{
    private $callback;
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }
    public function evaluateValue($key, $value)
    {
        if (call_user_func($this->callback, $value, $key) === true) {
            return $value;
        } else {
            return null;
        }
    }
}

class _Collection_Callback_Map implements _Collection_Callback
{
    private  $callback;
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }
    public function evaluateValue($key, $value)
    {
        return call_user_func($this->callback, $value, $key);
    }
}
