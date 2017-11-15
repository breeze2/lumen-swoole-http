# 使用

## Bootstrap文件
首先要复制`swoole.php`文件到`boostarp`目录下：

```cmd
$ cd /PATH/TO/LUMEN/PROJECT
$ cp vendor/breeze2/lumen-swoole-http/bootstrap/swoole.php ./bootstrap/
```

`bootstrap/swoole.php`与Lumen的`bootstrap/app.php`唯一区别是：

```php
// bootstrap/swoole.php
$app = new BL\SwooleHttp\Application(
    realpath(__DIR__.'/../')
);

// bootstrap/app.php
$app = new Laravel\Lumen\Application(
    realpath(__DIR__.'/../')
);
```

*(选)* 将`public/index.php`改为以下内容，则以后只需维护`bootstrap/swoole.php`文件就能使得项目能在NginX服务器与Swoole服务器间无修改切换：

```php
<?php
$app = require __DIR__.'/../bootstrap/swoole.php';
$app->run();
```

## 可用命令

```cmd
$ cd /PATH/TO/LUMEN/PROJECT
$ vendor/bin/swoole-http start   # 启动Swoole服务器
$ vendor/bin/swoole-http stop    # 关闭Swoole服务器
$ vendor/bin/swoole-http restart # 重启Swoole服务器
$ vendor/bin/swoole-http reload  # 重载Swoole服务器
$ vendor/bin/swoole-http status  # Swoole服务器状态
```
