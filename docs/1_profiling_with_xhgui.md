# \*XHGUI性能分析

## XHGUI
[XHGUI](https://github.com/perftools/xhgui)，是一个XHProf数据可视化工具，数据存储基于MongoDB。

## XHProf
[XHProf](http://pecl.php.net/package/xhprof)，是一个PHP性能跟踪工具。目前PHP7用的是[tideways/php-xhprof-extension](https://github.com/tideways/php-xhprof-extension)。

## 在lumen-swoole-http中使用XHGUI

### 安装XHProf扩展
给当前PHP安装XHProf扩展，比如PHP7：
```cmd
$ git clone https://github.com/tideways/php-xhprof-extension.git
$ cd php-xhprof-extension
$ phpize
$ ./configure
$ make
$ sudo make install
```
在PHP配置中添加
```
extension=tideways_xhprof.so
```

### 安装MongoDB连接代码库
在项目路径下，安装MongoDB连接代码库：
```cmd
$ cd /PATH/TO/PROJECT
$ composer require alcaeus/mongo-php-adapter
```
注意这个代码库要求当前PHP安装[mongo](http://pecl.php.net/package/mongo)扩展（PHP7对应[mongodb](http://pecl.php.net/package/mongodb)）。

### 配置xhgui.php
在项目路径下，添加`config/xhgui.php`文件，内容大概如下，主要是MongoDB的连接信息：
```php
<?php

return [
    'debug' => false,
    'mode' => 'development',

    'save.handler' => 'mongodb',
    'db.host' => 'mongodb://127.0.0.1:27017',
    'db.db' => 'xhprof',

    'db.options' => array(),
    'templates.path' => dirname(__DIR__) . '/src/templates',
    'date.format' => 'M jS H:i:s',
    'detail.count' => 6,
    'page.limit' => 25,

    'profiler.enable' => function() {
        return rand(1, 100) === 42;
    },
    'profiler.simple_url' => function($url) {
        return preg_replace('/\=\d+/', '', $url);
    },
    'profiler.options' => array(),

];
```

### 开启XHProf数据收集
在项目路径下，编辑`.env`文件，添加：
```env
SWOOLE_HTTP_XHGUI_COLLECT = true
```
重启Swoole HTTP服务器，每个请求处理的性能追踪数据都会保存到MongoDB中。
注意：若是使用后置异步协程，性能追踪会在异步协程开始前结束。

## 查看分析XHProf数据
下载XHGUI源码：
```cmd
$ git clone https://github.com/perftools/xhgui.git
```

修改配置文件（配置应该和上面的`xhgui.php`一样）：
```cmd
$ cd xhgui
$ cp config/config.default.php config/config.php
$ vi config/config.php
```

然后启动XHGUI服务，（浏览器访问`http://127.0.0.1:8000/`）便能查看MongoDB里的性能追踪数据：
```cmd
$ php -S 127.0.0.1:8000 -t webroot
```

## 最后
没必要在正式运行环境中进行性能追踪分析，在本地或者测试机上进行即可。