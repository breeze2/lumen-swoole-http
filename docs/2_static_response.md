# 静态输出

## 测试环境
* 操作系统：ubuntu16.04x64
* 内存：虚拟4GB
* CPU：虚拟4核3.0GHz
* NginX：v1.10.3
* PHP：v7.0.22
* Swoole：v1.9.2

## 测试工具
ApcacheBench

## 测试对象
* NginX(4worker) + HTML
* NginX(4worker) + PHP-FPM(static100children) + Lumen
* Swoole(4worker) + Lumen

## 测试结果
2000并发500000请求，不利用HTTP Keepalive，平均QPS：

```
NginX + HTML               QPS：25883.44
NginX + PHP-FPM + Lumen    QPS：828.36
Swoole + Lumen             QPS：13647.75
```

2000并发500000请求，利用HTTP Keepalive，平均QPS：

```
NginX + HTML               QPS：86843.11
NginX + PHP-FPM + Lumen    QPS：894.06
Swoole + Lumen             QPS：18183.43
```