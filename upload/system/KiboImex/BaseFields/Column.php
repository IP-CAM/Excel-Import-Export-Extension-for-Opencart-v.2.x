<?php
namespace KiboImex\BaseFields;

use KiboImex\ExportRow;

abstract class Column extends Base {
    public function export(array $product): ExportRow {
        return ExportRow::withField(
            $this->getOrder(),
            $this->getLabel(),
            $this->getValue($product),
        );
    }

    public function import(int $productId, ExportRow $row): void {
        if (!isset($row[$this->getLabel()])) {
            return;
        }

        $this->updateValue($productId, $row[$this->getLabel()]);
    }

    protected function warnInvalidValue(): void {
        $this->ctl->addWarning("Ongeldige invoer in veld: {$this->getLabel()}");
    }

    abstract protected function getOrder(): int;

    abstract protected function getLabel(): string;

    abstract protected function getValue(array $product): string;

    abstract protected function updateValue(int $productId, string $value): void;
}
