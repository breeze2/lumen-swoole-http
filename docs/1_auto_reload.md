# 热更新

使用inotify监视项目下所有PHP文件，文件更新时自动重载Swoole服务器。

安装inotify

```cmd
$ pecl install inotify
```

启动监视
```cmd
$ cd /PATH/TO/LUMEN/PROJECT
$ vendor/bin/swoole-http auto-reload
```