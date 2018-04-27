# 接口幂等

## 安装

```shell
composer require pfinal/idempotent
```


## 创建表

```sql

CREATE TABLE `idempotent` (
    `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
    `seq` varchar(50) NOT NULL DEFAULT '' COMMENT '请求序号',
    `response` longtext  COMMENT '响应内容',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ind_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT '幂等';
```

## 调用实例

```php
<?php

include __DIR__ . '/vendor/autoload.php';


//请求唯一标识
$seq = 'c4ca4238a0b923820dcc509a6f75849a';

\PFinal\Idempotent\Idempotent::$config['db.config'] = [
    'dsn' => 'mysql:host=127.0.0.1;dbname=test',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'tablePrefix' => '',
];

$result = \PFinal\Idempotent\Idempotent::run($seq, function () {
    //你自己的业务
    return time();
});

//同一个$seq多次请求，将得到相同的结果
echo $result;

```