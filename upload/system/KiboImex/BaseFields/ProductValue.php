<?php

namespace KiboImex\BaseFields;

use ControllerCatalogKiboimex;
use KiboImex\Helpers;

abstract class ProductValue extends Column {

    public function __construct(ControllerCatalogKiboimex $controller) {
        parent::__construct($controller);
        Helpers::requireColumn($this->ctl->db, 'product', $this->getColumn());
    }

    public function getValue(array $product): string {
        return $this->valueToText($product[$this->getColumn()]);
    }

    protected function updateValue(int $productId, string $text): void {
        Helpers::update(
            $this->ctl->db,
            'product',
            ['product_id' => $productId],
            [$this->getColumn() => $this->textToValue($text)]
        );
    }

    abstract protected function getColumn(): string;

    abstract protected function valueToText($value): string;

    abstract protected function textToValue(string $text);

}