<?php

spl_autoload_register(function ($class) {
    $pieces = explode('\\', $class);
    if (strcasecmp('KiboImex', array_shift($pieces)) || !$pieces) {
        return;
    }
    $path = __DIR__ . '/' . implode('/', $pieces) . '.php';
    if (file_exists($path)) {
        /** @noinspection PhpIncludeInspection */
        require_once $path;
    }
});