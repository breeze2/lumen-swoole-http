# Lumen Swoole HTTP

> A bridge from Swoole to Lumen.

## 设计理念
* [用Swoole HTTP服务器运行Lumen项目的实现方法](https://blog.breezelin.cn/swoole-lumen-run-lumen-with-swoole.html)
* [在Lumen项目中使用Swoole异步MySQL客户端的实现方法](https://blog.breezelin.cn/swoole-lumen-use-async-mysql-client-in-lumen.html)
* [在Lumen项目中自定义后置异步协程](https://blog.breezelin.cn/swoole-lumen-define-post-processing-coroutine.html)

## Require
* PHP >= 7.0.0
* Lumen >= 5.5.0
* Swoole >= 1.9.2

## Install

```cmd
$ cd /PATH/TO/LUMEN/PROJECT
$ composer require breeze2/lumen-swoole-http
$ vendor/breeze2/lumen-swoole-http/bootstrap/swoole.php ./bootstrap/
```

## Usage

```
$ vendor/bin/swoole-http start   
$ vendor/bin/swoole-http stop    
$ vendor/bin/swoole-http restart 
$ vendor/bin/swoole-http reload  
$ vendor/bin/swoole-http status  
```

## \*Coroutine for Slow Query

edit `vendor/breeze2/lumen-swoole-http/bootstrap/swoole.php`

```php
...
$app = new BL\SwooleHttp\AsyncApplication(
    realpath(__DIR__.'/../')
);

$app->withFacades();

$app->withEloquent();
...
```

in your controller

```php
pubilc function test() {
    $results = yield new \BL\SwooleHttp\Database\SlowQuery('select sleep(1);');
    $results = yield User::select('*')->yieldGet(); // User extends \BL\SwooleHttp\Database\Model
    response()->json($results);
}

```

