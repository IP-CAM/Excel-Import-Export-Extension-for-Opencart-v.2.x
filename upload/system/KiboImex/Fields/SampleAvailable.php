<?php

namespace KiboImex\Fields;

use KiboImex\BaseFields\ProductBool;

class SampleAvailable extends ProductBool {

    protected function getOrder(): int {
        return 1501;
    }

    protected function getLabel(): string {
        return 'Staalaanvraag mogelijk';
    }

    protected function getColumn(): string {
        return 'sample_available';
    }

}