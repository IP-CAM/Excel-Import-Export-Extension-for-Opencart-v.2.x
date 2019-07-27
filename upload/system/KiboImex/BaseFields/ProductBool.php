<?php

namespace KiboImex\BaseFields;

abstract class ProductBool extends ProductValue {

    protected function valueToText($value): string {
        return $value ? 'ja' : '';
    }

    protected function textToValue(string $text) {
        if (!strcasecmp($text, 'ja')) {
            return 1;
        } elseif (!strcasecmp($text, 'nee') || $text == '') {
            return 0;
        } else {
            $this->warnInvalidValue();
            return 0;
        }
    }

}