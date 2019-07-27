<?php

namespace KiboImex\Fields;

use KiboImex\BaseFields\ProductValue;

class Unit extends ProductValue {

    const VALUES = [
        '/ m²',
        '/ stuk',
        '/ m¹',
    ];

    protected function getColumn(): string {
        return 'unit';
    }

    protected function getOrder(): int {
        return 1031;
    }

    protected function getLabel(): string {
        return 'Eenheid';
    }

    protected function valueToText($value): string {
        if (in_array($value, self::VALUES)) {
            return $value;
        }

        return '';
    }

    protected function textToValue(string $text) {
        if ($text == '') {
            return '';
        }

        foreach (self::VALUES as $value) {
            if ($this->normalize($value) == $this->normalize($text)) {
                return $value;
            }
        }

        $this->warnInvalidValue();

        return '';
    }

    private function normalize($v): string {
        $v = (string) $v;
        $v = preg_replace('~^/*\s*~', '', $v);
        $v = strtr($v, [
            '¹' => '1',
            '²' => '2',
        ]);
        return $v;
    }

}