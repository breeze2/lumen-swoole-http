# 使用Lumen+Swoole之后

使用Lumen+Swoole之后，有些问题需要注意。

Swoole服务器是以CLI方式运行，长驻内存的，所需PHP脚本在开始时已加载完，与NignX+PHP-FPM的运行方式不同。本地开发时，修改了PHP脚本必须重载Swoole服务器才能生效。不过，通过监控文件状态，可以实现代码热更新，推荐使用[swoole/auto_reload](https://github.com/swoole/auto_reload)。

另外，在Swoole进程中，不要太依赖全局变量，如`$_SERVER`、`$_COOKIE`之类。在Swoole或Lumen中会有指定的方法可以获取这些变量对应的值。响应结果只能通过`$response->end()`输出，`echo`、`var_dump()`等这类输出只会打印到当前终端。