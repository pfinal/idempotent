<?php

include __DIR__ . '/../vendor/autoload.php';

$seq = 'c4ca4238a0b923820dcc509a6f75849a';

\PFinal\Idempotent\Idempotent::$config['db.config'] = [
    'dsn' => 'mysql:host=127.0.0.1;dbname=test',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'tablePrefix' => '',
];

$result = \PFinal\Idempotent\Idempotent::run($seq, function () {
    return time();
});

echo $result;
