<?php

namespace KiboImex\Fields;

use KiboImex\BaseFields\ProductBool;

class OnlineAvailable extends ProductBool {

    protected function getOrder(): int {
        return 1500;
    }

    protected function getLabel(): string {
        return 'Online bestellen';
    }

    protected function getColumn(): string {
        return 'online_available';
    }

}