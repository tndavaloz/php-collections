<?php

namespace Collections;

use Collections\Exceptions\InvalidArgumentException;
use Collections\Exceptions\OutOfRangeException;
use ArrayIterator;

/**
 * A collection of objects with a specified class or interface
 */
class Collection implements CollectionInterface
{
    use TypeValidator;

    /**
     * The collection's encapsulated array
     *
     * @var array
     */
    protected $items;

    /**
     * The name of the object, either class or interface, that the list works with
     *
     * @var string
     */
    private $type;

    /**
     * Instantiates the collection by specifying what type of Object will be used.
     *
     * @param $type
     * @param array $items
     * @throws InvalidArgumentException
     */
    public function __construct($type, $items = [])
    {
        $type = $this->determineType($type);
        $this->type = $type;

        if ($items) {
          $this->validateItems($items, $this->type);
        }

        $this->items = $items;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param $item
     * @return static
     * @throws InvalidArgumentException
     */
    public function add($item)
    {
        $this->validateItem($item, $this->type);

        $items = $this->items;
        $items[] = $item;

        return new static($this->type, $items);
    }

    /**
     * @return static
     */
    public function clear()
    {
        return new static($this->type);
    }

    /**
     * @param callable $condition
     * @return bool
     */
    public function contains(callable $condition)
    {
        return (bool) $this->find($condition);
    }

    /**
     * @param callable $condition
     * @return bool
     */
    public function find(callable $condition)
    {
        $index = $this->findIndex($condition);

        return $index == -1 ? false : $this->items[$index];
    }

    /**
     * @param callable $condition
     * @return int
     */
    public function findIndex(callable $condition)
    {
        $index = -1;

        for ($i = 0; $i < count($this->items); $i++) {
            if ($condition($this->at($i))) {
                $index = $i;
                break;
            }
        }

        return $index;
    }

    /**
     * @param $index
     * @return mixed
     * @throws OutOfRangeException
     */
    public function at($index)
    {
        $this->validateIndex($index);

        return $this->items[$index];
    }

    /**
     * Validates a number to be used as an index
     *
     * @param integer $index The number to be validated as an index
     * @throws OutOfRangeException
     * @throws InvalidArgumentException
     */
    private function validateIndex($index)
    {
        $exists = $this->indexExists($index);

        if (!$exists) {
            throw new OutOfRangeException("Index out of bounds of collection");
        }
    }

    /**
     * @param $index
     * @return bool
     * @throws InvalidArgumentException
     */
    public function indexExists($index)
    {
        if (!is_int($index)) {
            throw new InvalidArgumentException("Index must be an integer");
        }

        if ($index < 0) {
            throw new InvalidArgumentException("Index must be a non-negative integer");
        }

        return $index < $this->count();
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * @param callable $condition
     * @return static
     */
    public function filter(callable $condition)
    {
        $items = [];

        foreach ($this->items as $item) {
            if ($condition($item)) {
                $items[] = $item;
            }
        }

        return new static($this->type, $items);
    }

    /**
     * @param callable $condition
     * @return bool
     */
    public function findLast(callable $condition)
    {
        $index = $this->findLastIndex($condition);

        return $index == -1 ? false : $this->items[$index];
    }

    /**
     * @param callable $condition
     * @return int
     */
    public function findLastIndex(callable $condition)
    {
        $index = -1;

        for ($i = count($this->items) - 1; $i >= 0; $i--) {
            if ($condition($this->items[$i])) {
                $index = $i;
                break;
            }
        }

        return $index;
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @param $start
     * @param $end
     * @return static
     * @throws InvalidArgumentException
     */
    public function slice($start, $end)
    {
        if (!is_integer($start) || $start < 0) {
            throw new InvalidArgumentException("Start must be a non-negative integer");
        }

        if (!is_integer($end) || $end < 0) {
            throw new InvalidArgumentException("End must be a positive integer");
        }

        if ($start > $end) {
            throw new InvalidArgumentException("End must be greater than start");
        }

        if ($end > $this->count() + 1) {
            throw new InvalidArgumentException("End must be less than the count of the items in the Collection");
        }

        $length = $end - $start + 1;

        $subsetItems = array_slice($this->items, $start, $length);

        return new static($this->type, $subsetItems);
    }

    /**
     * @param $index
     * @param $item
     * @throws InvalidArgumentException
     * @throws OutOfRangeException
     */
    public function insert($index, $item)
    {
        $this->validateIndex($index);
        $this->validateItem($item, $this->type);

        $partA = array_slice($this->items, 0, $index);
        $partB = array_slice($this->items, $index, count($this->items));
        $partA[] = $item;
        $this->items = array_merge($partA, $partB);
    }

    /**
     * @param $index
     * @param array $items
     * @throws OutOfRangeException
     */
    public function insertRange($index, array $items)
    {
        $this->validateIndex($index);
        $this->validateItems($items, $this->type);

        //To work with negative index, get the positive relation to 0 index
        $index < 0 && $index = $this->count() + $index + 1;

        $partA = array_slice($this->items, 0, $index);
        $partB = array_slice($this->items, $index, count($this->items));

        $this->items = array_merge($partA, $items);
        $this->items = array_merge($this->items, $partB);
    }

    /**
     * @param callable $condition
     * @return Collection
     */
    public function without(callable $condition)
    {
        $inverse = function($item) use ($condition) {
            return !$condition($item);
        };

        return $this->filter($inverse);
    }

    /**
     * @param $index
     * @return static
     * @throws OutOfRangeException
     */
    public function removeAt($index)
    {
        $this->validateIndex($index);
        $items = $this->items;

        $partA = array_slice($items, 0, $index);
        $partB = array_slice($items, $index + 1, count($items));
        $items = array_merge($partA, $partB);

        return new static($this->type, $items);
    }

    /**
     * @return static
     */
    public function reverse()
    {
        return new static($this->getType(), array_reverse($this->items));
    }

    /**
     * @param callable $callback
     * @return static
     */
    public function sort(callable $callback)
    {
        $items = $this->items;

        usort($items, $callback);

        return new static($this->type, $items);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->items;
    }

    /**
     * @param callable $callable
     * @param null $initial
     * @return mixed
     */
    public function reduce(callable $callable, $initial = null)
    {
        return array_reduce($this->items, $callable, $initial);
    }

    /**
     * @param callable $condition
     * @return bool
     */
    public function every(callable $condition)
    {
        $response = true;

        foreach ($this->items as $item) {
            $result = call_user_func($condition, $item);
            if ($result === false) {
                $response = false;
                break;
            }
        }

        return $response;
    }

    /**
     * @param $num
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function drop($num)
    {
        return $this->slice($num, $this->count());
    }

    /**
     * @param $num
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function dropRight($num)
    {
        return ($num != $this->count())
                    ? $this->slice(0, $this->count() - $num - 1)
                    : $this->clear();
    }

    /**
     * @param callable $condition
     * @return Collection
     */
    public function dropWhile(callable $condition)
    {
        $count = $this->countWhileTrue($condition);

        return ($count) ? $this->drop($count) : $this;
    }

    /**
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function tail()
    {
       return $this->slice(1,$this->count());
    }

    /**
     * @param $num
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function take($num)
    {
        return $this->slice(0, $num - 1);
    }

    /**
     * @param $num
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function takeRight($num)
    {
        return $this->slice($this->count() - $num, $this->count());
    }

    /**
     * @param callable $condition
     * @return int
     */
    protected function countWhileTrue(callable $condition)
    {
        $count = 0;

        foreach ($this->items as $item) {
            if (!$condition($item)) {
              break;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param callable $condition
     * @return Collection
     */
    public function takeWhile(callable $condition)
    {
        $count = $this->countWhileTrue($condition);

        return ($count) ? $this->take($count) : $this->clear();
    }

    /**
     * @param callable $callable
     */
    public function each(callable $callable)
    {
        foreach ($this->items as $item) {
            $callable($item);
        }
    }

    /**
     * @param callable $callable
     * @return static
     */
    public function map(callable $callable)
    {
        $items = [];
        $type = null;

        foreach ($this->items as $item) {
             $result = $callable($item);

            if (!isset($type)) {
                $type =  gettype($result);
            }

            $items[] = $result;
        }

        return new static($type, $items);
    }

    /**
     * @param callable $callable
     * @param null $initial
     * @return mixed
     */
    public function reduceRight(callable $callable, $initial = null)
    {
        $reverse = array_reverse($this->items);

        return array_reduce($reverse, $callable, $initial);
    }

    /**
     * @return static
     */
    public function shuffle()
    {
        $items = $this->items;
        shuffle($items);

        return new static($this->getType(), $items);
    }

    /**
     * @param $items
     * @return static
     * @throws InvalidArgumentException
     */
    public function merge($items)
    {
        if ($items instanceof static) {
            $items = $items->toArray();
        }

        if (!is_array($items)) {
            throw new InvalidArgumentException("Merge must be given array or Collection");
        }

        $this->validateItems($items, $this->type);
        $newItems = array_merge($this->items, $items);

        return new static($this->type, $newItems);
    }
}
