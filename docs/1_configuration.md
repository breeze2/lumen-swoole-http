# 配置

## Lumen插件参数选项
在项目`.env`文件设置

```env
# SWOOLE_HTTP
SWOOLE_HTTP_HOST=127.0.0.1         # Swoole服务器IP地址，默认127.0.0.1
SWOOLE_HTTP_PORT=9080              # Swoole服务器监听端口，默认9080
SWOOLE_HTTP_GZIP=1                 # Swoole服务器gzip压缩级别，默认1，留空则不开启gzip压缩功能
SWOOLE_HTTP_GZIP_MIN_LENGTH=1024   # Swoole服务器开启gzip压缩功能的阈值，默认1024，单位Byte
SWOOLE_HTTP_STATIC_RESOURCES=false # public目录下静态资源的访问权限，默认false，若设为true，请删除public目录下敏感文件
SWOOLE_HTTP_PID_FILE=app/swoole-http.pid # Swoole服务器主进程ID存放位置，默认app/swoole-http.pid
SWOOLE_HTTP_STATS_URI=/swoole-http-stats # Swoole服务器统计信息的uri，默认/swoole-http-stats，留空则不可访问
SWOOLE_HTTP_REQUEST_LOG_PATH=/var/log/sh # Swoole服务器请求日志保存目录，须是绝对路径，并对目录有读写权限，留空则不保存请求日志，默认留空

```

## Swoole服务器参数选项
请参考[Swoole Server配置选项](https://wiki.swoole.com/wiki/page/274.html)，以下参数改为大写，加上前缀`SWOOLE_HTTP_`，在项目`.env`设置即可：

```plain
reactor_num
worker_num        # 默认1
max_request
max_conn          # 默认255
task_worker_num
task_ipc_mode
task_max_request
task_tmpdir
dispatch_mode
dispatch_func
message_queue_key
daemonize         # true
backlog
log_file          # 填写绝对路径，默认当前项目storage/logs/swoole-http.log
log_level
heartbeat_check_interval
heartbeat_idle_time
open_eof_check
open_eof_split
package_eof
open_length_check
package_length_type
package_length_func
package_max_length
open_cpu_affinity
cpu_affinity_ignore
open_tcp_nodelay
tcp_defer_accept
ssl_cert_file
ssl_method
ssl_ciphers
user
group
chroot
pid_file          # 默认app/swoole-http.pid
pipe_buffer_size
buffer_output_size
socket_buffer_size
enable_unsafe_event
discard_timeout_request
enable_reuse_port
ssl_ciphers
enable_delay_receive
open_http_protocol
open_http2_protocol
open_websocket_protocol
open_mqtt_protocol
reload_async
tcp_fastopen
```
