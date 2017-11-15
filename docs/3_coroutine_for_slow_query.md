# 慢查询协程

> 这里说的数据库特指MySQL，其他数据库暂不支持；目前只实现读协程，写协程在后续完善。

## 原理
慢查询协程是基于迭代生成器实现的，建议阅读[《Cooperative multitasking using coroutines (in PHP!)》](http://nikic.github.io/2012/12/22/Cooperative-multitasking-using-coroutines-in-PHP.html)（中文版[《在PHP中使用协程实现多任务调度》](http://www.laruence.com/2015/05/28/3038.html)），了解更多。

Lumen框架处理用户请求的代码分了很多层，我们将控制器视为开发者处理数据的最后一层（路由层和中间件层可以自行兼容），在Swoole服务器捕获Lumen控制器里的`yield`声明，跟随`yield`后面的必须是`BL\SwooleHttp\Database\EloquentBuilder`或`BL\SwooleHttp\Database\SlowQuery`（目前只支持这两个）的实例对象，这是生成器的当前值，若是符合前面两种类型，Swoole服务器则启用协程异步执行慢查询，将查询结果传入生成器，否则直接将当前值传入生成器，继续往下执行。

## 配置
按照[说明](/#/1_installation)安装配置lumen-swoole-http后，并未能直接使用慢查询协程，因为默认不开启慢查询协程。除非明确知道查询语句的占用时间较长，否则不需要使用慢查询协程。比如获取当前用户信息，一般都是用主键查询，速度不会慢，若是使用协程查询开销更大。

打开`bootstrap/swoole.php`，从
```php
$app = new BL\SwooleHttp\Application(
    realpath(__DIR__.'/../')
);
// $app->withFacades();
// $app->withEloquent();
```
改为：
```php
$app = new BL\SwooleHttp\AsyncApplication(
    realpath(__DIR__.'/../')
);
$app->withFacades();
$app->withEloquent();

```

为了控制协程数量，现提供一个环境参数`SWOOLE_HTTP_MAX_COROUTINE`，可在`.env`中设置，默认值是10。若当前协程数量已到达最大值，新的慢查询会放在主进程里同步阻塞执行。

## 使用
原本同步阻塞的慢查询：
```php
<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $a = app('db')->select('select sleep(1);');
        $b = User::select('*')->get();
        response()->json($b);
    }
}
```
使用慢查询协程：
```php
<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\User;
use BL\SwooleHttp\Database\SlowQuery;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $a = yield new SlowQuery('select sleep(1);');
        $b = yield User::select('*')->yieldGet(); // 注意User须继承自\BL\SwooleHttp\Database\Model
        response()->json($b);
    }
}
```

## 测试效果
1. 16worker的Swoole服务器，并发执行`select sleep(1);`请求的最大效率是15.72rps；
2. 16worker x 10coroutine的Swoole服务器，并发执行`select sleep(1);`请求的最大效率是151.93rps。

对于单个用户，每个请求都需要1秒时间，开启慢查询协程的Swoole服务器每秒可以支持160个用户同时请求，而
普通Swoole服务器只能16个用户同时请求。为什么说最大效率呢？因为当并发量远大于worker数目 x coroutine数目时，开启慢查询协程的Swoole服务器的效率会跌向普通Swoole服务器。

