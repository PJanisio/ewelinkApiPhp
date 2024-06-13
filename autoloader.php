<?php

// Autoloader function to load classes automatically
spl_autoload_register(function ($class) {
    $base_dir = __DIR__ . '/src/';
    $file = $base_dir . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
