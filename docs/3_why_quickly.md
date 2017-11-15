# 为什么快

在数据读写的压力测试中，可以明显看出Swoole(4worker)+Lumen+MySQL架构快于NginX(4worker)+PHP-FPM(static200children)+Lumen+MySQL架构，那为什么快呢？
首先，Lumen框架连接MySQL使用的是单例短连接，所以4worker的Swoole服务器一共只有4个数据库连接，短时间内所有用户请求都是在复用这4个数据库连接；相比200children的PHP-FPM，每个用户请求都会重新建立数据库连接，Swoole服务器节省了不少时间开销。

再者，NginX与PHP-FPM之间需要创建连接才能传递信息，PHP-FPM收到请求后又要重新初始化Lumen框架，而这些Swoole服务器在启动的时候已经准备好了。实际上，这也是快的主要原因。



