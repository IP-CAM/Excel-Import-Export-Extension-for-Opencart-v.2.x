<?php

namespace KiboImex;

use ArrayAccess;
use Generator;
use IteratorAggregate;
use LogicException;
use RuntimeException;

class ExportRow implements IteratorAggregate, ArrayAccess {

    private $orders = [];
    private $values = [];

    public static function withField(int $order, string $label, $value): self {
        return (new self)->addField($order, $label, $value);
    }

    public function offsetExists($offset) {
        return $this->hasField((string) $offset);
    }

    public function hasField(string $label): bool {
        return isset($this->values[$label]);
    }

    public function offsetGet($offset) {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet($offset, $value) {
        throw new LogicException("Use addField or setField");
    }

    public function offsetUnset($offset) {
        throw new LogicException("Not implemented");
    }

    public function merge(ExportRow $other): ExportRow {
        $newRow = $this;
        foreach ($other->values as $label => $value) {
            $newRow = $newRow->addField($other->orders[$label], $label, $value);
        }
        return $newRow;
    }

    public function addField(int $order, string $label, $value): self {
        if (isset($this->values[$label])) {
            throw new RuntimeException("Export field labels must be unique: $label");
        }
        return $this->setField($order, $label, $value);
    }

    public function setField(int $order, string $label, $value): self {
        $newRow = clone $this;
        $newRow->orders[$label] = $order;
        $newRow->values[$label] = (string) $value;
        return $newRow;
    }

    public function mergeReplace(ExportRow $other): ExportRow {
        $newRow = $this;
        foreach ($other->values as $label => $value) {
            $newRow = $newRow->setField($other->orders[$label], $label, $value);
        }
        return $newRow;
    }

    public function getIterator(): Generator {
        $i = 0;
        $indices = [];
        $orders = [];
        $labels = [];
        foreach ($this->orders as $label => $order) {
            $indices[] = $i++;
            $orders[] = $order;
            $labels[] = $label;
        }
        array_multisort($orders, $indices, $labels);
        foreach ($labels as $label) {
            yield $label => $this->values[$label];
        }
    }

}