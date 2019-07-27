<?php

namespace KiboImex\BaseFields;

use ControllerCatalogKiboimex;
use KiboImex\ExportRow;
use KiboImex\Helpers;

abstract class ProductValue extends Base {

    public function __construct(ControllerCatalogKiboimex $controller) {
        parent::__construct($controller);
        Helpers::requireColumn($this->ctl->db, 'product', $this->getColumn());
    }

    abstract protected function getColumn(): string;

    public function export(array $product): ExportRow {
        return ExportRow::withField(
            $this->getOrder(),
            $this->getLabel(),
            $this->valueToText($product[$this->getColumn()])
        );
    }

    abstract protected function getOrder(): int;

    abstract protected function getLabel(): string;

    abstract protected function valueToText($value): string;

    public function import(int $productId, ExportRow $row) {
        if (!isset($row[$this->getLabel()])) {
            return;
        }

        Helpers::update(
            $this->ctl->db,
            'product',
            ['product_id' => $productId],
            [$this->getColumn() => $this->textToValue($row[$this->getLabel()])]
        );
    }

    abstract protected function textToValue(string $text);

    protected function warnInvalidValue() {
        return $this->ctl->addWarning("Ongeldige invoer in veld: {$this->getLabel()}");
    }

}