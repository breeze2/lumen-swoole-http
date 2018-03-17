# \*后置异步协程

## 配置
按照[说明](/#/1_installation)安装配置lumen-swoole-http后，并未能直接使用后置异步协程，因为默认不开启后置异步协程。除非明确知道当前操作的IO等待时间较长，否则不需要使用后置异步协程。

打开`bootstrap/swoole.php`，从
```php
$app = new BL\SwooleHttp\Application(
    realpath(__DIR__.'/../')
);

```
改为：
```php
$app = new BL\SwooleHttp\AsyncApplication(
    realpath(__DIR__.'/../')
);

```

为了控制协程数量，现提供一个环境参数`SWOOLE_HTTP_MAX_COROUTINE`，可在`.env`中设置，默认值是10。若当前协程数量已到达最大值，新的慢查询会放在主进程里同步阻塞执行。

## CustomAsyncProcess
lumen-swoole-http中已经定义了抽象类`CustomAsyncProcess`，在处理用户请求时，若是捕获到`CustomAsyncProcess`类的对象，程序便会按照`CustomAsyncProcess`对象的指定方法执行。部分代码：
```php
<?php
    ...
    $current_value = $last_generator->current();
    if ($current_value instanceof CustomAsyncProcess) {
        if ($worker->canDoCoroutine()) {
            $worker->upCoroutineNum();
            return $current_value->runAsyncTask($request, $response, $worker, $this->scheduler, $last_generator);
        } else {
            return $current_value->runNormalTask($request, $response, $worker, $this->scheduler, $last_generator);
        }
    }
    ...
```


## 一个简单的后置协程
我们先来实现一个简单的后置协程`EasyProcess`，继承自`CustomAsyncProcess`，需要实现两个抽象方法：
```php
<?php
namespace App\http\AfterCoroutines;
use BL\SwooleHttp\Service;
use BL\SwooleHttp\Coroutine\SimpleSerialScheduler;
use Generator;
use swoole_http_request as SwooleHttpRequest;
use swoole_http_response as SwooleHttpResponse;

class EasyProcess extends \BL\SwooleHttp\Coroutine\CustomAsyncProcess
{
    // 因为有协程数量限制，达到最大协程数量时，会调用runNormalTask方法
    public function runNormalTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, SimpleSerialScheduler $scheduler, Generator $last_generator)
    {
        $value = 2;
        // 给最后一层生成器，传入$value值，并递归导出整个生成器套层的最终值$final
        $final = $this->fullRunScheduler($scheduler, $last_generator, $value);
        $http_response = $final;
        // 注意：runNormalTask方法中使用makeNormalResponse方法响应用户请求
        $this->makeNormalResponse($request, $response, $worker, $http_response);
    }

    // 没达到最大协程数量时，会调用runAsyncTask方法
    public function runAsyncTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, SimpleSerialScheduler $scheduler, Generator $last_generator)
    {
        $value = 1;
        // 给最后一层生成器，传入$value值，并递归导出整个生成器套层的最终值$final
        $final = $this->fullRunScheduler($scheduler, $last_generator, $value);
        $http_response = $final;
        // 注意：runAsyncTask方法中使用makeAsyncResponse方法响应用户请求
        $this->makeAsyncResponse($request, $response, $worker, $http_response);
    }
}
```

然后就可以在Controller中使用这个后置协程：
```php
<?php
namespace App\Http\Controllers;
use App\http\AfterCoroutines\EasyProcess;
use App\Http\Controllers\Controller;

class TestController extends Controller
{
    public function test()
    {
        $a = yield new EasyProcess();
        response()->json($a);
    }
}
```

当访问`TestController@test`对应的路由时，得到的返回结果有可能是1，也有可能是2，视当时协程数量而定。1或2这个结果在`TestController@test`层面上是未曾知的，是由`EasyProcess`这个后置协程产出的。

`EasyProcess`不算一个异步协程，无论`runNormalTask`方法或者`runAsyncTask`方法，都是同步执行的。若想实现异步执行，必须使用Swoole提供的异步客户端，详细请查看Swoole官方文档[AsyncIO](https://wiki.swoole.com/wiki/page/p-async.html)。

下面介绍如何实现一个后置的异步HTTP客户端协程。

## 一个后置的异步HTTP客户端协程
假设我们处理某个用户请求时，需要从远端链接`http://www.domain.com/api/data`获取一些数据，期间可能需要耗时几秒钟，那么怎样用后置异步协程实现来避免阻塞呢？

我们先来实现一个简单的后置协程`AysncHttpProcess`，继承自`CustomAsyncProcess`，需要实现两个抽象方法：
```php
<?php
namespace App\http\AfterCoroutines;
use BL\SwooleHttp\Service;
use BL\SwooleHttp\Coroutine\SimpleSerialScheduler;
use Generator;
use swoole_http_request as SwooleHttpRequest;
use swoole_http_response as SwooleHttpResponse;
use Swoole\Http2\Client as SwooleHttpClient;

class AysncHttpProcess extends \BL\SwooleHttp\Coroutine\CustomAsyncProcess
{
    public function $url;
    public function __construct($url)
    {
        $this->url = $url;
    }

    // 因为有协程数量限制，达到最大协程数量时，会调用runNormalTask方法
    public function runNormalTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, SimpleSerialScheduler $scheduler, Generator $last_generator)
    {
        $data = file_get_contents($this->url);
        // 给最后一层生成器，传入$value值，并递归导出整个生成器套层的最终值$final
        $final = $this->fullRunScheduler($scheduler, $last_generator, $data);
        $http_response = $final;
        // 注意：runNormalTask方法中使用makeNormalResponse方法响应用户请求
        $this->makeNormalResponse($request, $response, $worker, $http_response);
    }

    // 没达到最大协程数量时，会调用runAsyncTask方法
    public function runAsyncTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, SimpleSerialScheduler $scheduler, Generator $last_generator)
    {
        $client = new SwooleHttpClient();
        $caller = $this;
        $client->get($this->url, function ($o) use($client, $caller) {
            $data = $o->body;
            // 给最后一层生成器，传入$value值，并递归导出整个生成器套层的最终值$final
            $final = $caller->fullRunScheduler($scheduler, $last_generator, $value);
            $http_response = $final;
            // 注意：runAsyncTask方法中使用makeAsyncResponse方法响应用户请求
            $caller->makeAsyncResponse($request, $response, $worker, $http_response);
            $client->close();
        });
    }
}
```

然后就可以在Controller中使用这个后置协程：
```php
<?php
namespace App\Http\Controllers;
use App\http\AfterCoroutines\AysncHttpProcess;
use App\Http\Controllers\Controller;

class TestController extends Controller
{
    public function test()
    {
        $a = yield new AysncHttpProcess('http://www.domain.com/api/data');
        response()->json($a);
    }
}
```

就这样，在协程数量允许情况下，我们可以使用后置的异步HTTP客户端协程获取远端数据，并且避免了阻塞。
