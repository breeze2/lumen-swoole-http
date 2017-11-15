# 为什么选择Lumen+Swoole

[Swoole](https://www.swoole.com/)是PHP的一个扩展，主要填补PHP在网络通信和异步IO方面的不足。利用Swoole，可以很容易实现一个高性能HTTP服务器。但是使用Swoole后，代码风格会有改变，最好能通过中间整合，使得Swoole服务器可以兼容主流应用框架。

当下最流行的PHP应用框架自然是[Laravel](https://laravel.com/)，Laravel开发组织同时维护一个微框架叫[Lumen](https://lumen.laravel.com/)。Lumen是Laravel的简化版，两者的设计模式是一样的，不过Laravel大而杂，Lumen简而精，Lumen主要专注于构建无状态API。

要快，先要轻，所以选择Lumen来接合Swoole。