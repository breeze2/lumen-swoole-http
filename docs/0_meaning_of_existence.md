# 存在价值

> 我也曾思考过[lumen-swoole-http](https://github.com/breeze2/lumen-swoole-http)的存在价值……

* 首先，即使没有Swoole扩展，也会有人使用Lumen应用框架；然而`Swoole + Lumen`的整体性能明显由于`NginX + PHP-FPM + Lumen`，使用Lumen应用框架的人们应该选择`Swoole + Lumen`；
* 再者，`lumen-swoole-http`还可以在MySQL数据库[慢查询](/3_coroutine_for_slow_query.md)上略尽绵力；
* 前后分离的趋势下，微框架Lumen可能将会比Laravel更受青睐；
* 当然Lumen是Laravel的子框架，`lumen-swoole-http`要移植到Laravel上也挺容易（虽然我还没试过😂）；
* `lumen-swoole-http`可以兼容[DingoAPI](https://github.com/dingo/api)等常用的框架包（[这我倒试过](/4_work_with_dingo.md)）；
* ……

所以，`lumen-swoole-http`是有存在价值的。
