<?php
if (!class_exists('Enna\Framework\Facade')) {
    require __DIR__ . '/Facade.php';
}

if (!class_exists('Enna\Framework\Exception')) {
    return __DIR__ . '/Exception.php';
}