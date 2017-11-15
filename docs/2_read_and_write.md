# 数据读写

## 测试环境
* 操作系统：ubuntu16.04x64
* CPU：虚拟4核3.0GHz
* 内存：虚拟4GB
* NginX：1.10.3
* PHP：7.0.22
* Swoole：1.9.2
* MySQL：5.7.0

## 测试工具
ApcacheBench

## 测试对象
* NginX(4worker) + PHP-FPM(static200children) + Lumen + MySQL
* Swoole(4worker) + Lumen + MySQL

## 测试结果
200并发50000请求读，利用HTTP Keepalive，平均QPS：

```
NginX + PHP-FPM + Lumen + MySQL    QPS：521.56
Swoole + Lumen + MySQL             QPS：7509.99
```

200并发50000请求写，利用HTTP Keepalive，平均QPS：

```
NginX + PHP-FPM + Lumen + MySQL    QPS：449.44
Swoole + Lumen + MySQL             QPS：1253.93
```
