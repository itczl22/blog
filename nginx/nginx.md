# 高性能web服务器Nginx
## Nginx简介
- Nginx  
    - [Nginx官方介绍](http://nginx.org/en "Nginx官方介绍")
    - Nginx是一个网页服务器, 它能反向代理HTTP, HTTPS, SMTP, POP3, IMAP的协议链接, 以及一个负载均衡器和一个HTTP缓存.
- 特点
    - Nginx是一款面向性能设计的HTTP服务器, 相较于Apache、lighttpd具有占有内存少, 稳定性高等优势.
    - 与Apache不同, Nginx不采用每客户机一线程的设计模型, 而是充分使用异步逻辑, 削减了上下文调度开销, 所以并发服务能力更强.
    - 整体采用模块化设计, 有丰富的模块库和第三方模块库, 配置灵活.
    - 在Linux操作系统下, Nginx使用epoll事件模型, 得益于此, Nginx在Linux操作系统下效率相当高. 
    - Nginx在官方测试的结果中, 能够支持五万个平行连接, 而在实际的运作中可以支持二万至四万个平行链接.
- Nginx的模块  
    - 整体采用模块化设计是Nginx的一个重大特点, 甚至http服务器核心功能也是一个模块. 
    - 旧版本的Nginx的模块是静态的, 添加和删除模块都要对Nginx进行重新编译, 1.9.11以及更新的版本已经支持动态模块加载.

- Nginx与PHP集成
    - 自PHP-5.3.3起, PHP-FPM加入到了PHP核心, 编译时加上--enable-fpm即可提供支持.
    - PHP-FPM以守护进程在后台运行, Nginx响应请求后, 自行处理静态请求, PHP请求则经过fastcgi_pass交由PHP-FPM处理, 处理完毕后返回.
    - Nginx和PHP-FPM的组合, 是一种稳定、高效的PHP运行方式, 效率要比传统的Apache和mod_php高出不少.

## Nginx的内部结构
- Nginx执行流程
    - nginx在启动后, 在unix系统中会以daemon的方式在后台运行, 后台进程包含一个master进程和多个worker进程. 
    - master进程主要用来管理worker进程, 包含: 接收来自外界的信号, 向各worker进程发送信号, 监控worker进程的运行状态, 当worker进程退出后(异常情况下), 会自动重新启动新的worker进程. 
    - worker进程主要处理基本的网络事件, 多个worker进程之间是对等的, 他们同等竞争来自客户端的请求, 各进程互相之间是独立的. 一个请求, 只可能在一个worker进程中处理, 一个worker进程, 不可能处理其它进程的请求, 为了保证这一点在注册事件时必须. worker进程的个数是可以设置的, 一般我们会设置与机器cpu核数一致, 这里面的原因与nginx的进程模型以及事件处理模型是分不开的.
- Nginx的进程模型    
  ![Nginx的进程模型](/nginx/nginx-process-model.png "nginx的进程模型")
- http请求的处理流程  
  ![http请求的处理流程](/nginx/nginx-http.png "http请求的处理流程")

## Nginx的安装
- [Nginx官方教程](http://nginx.org/en/linux_packages.html "Nginx官方教程")
- 下载
```
wget http://nginx.org/download/nginx-1.9.5.tar.gz
```
- 解压
```
tar zxvf nginx-1.9.5.tar.gz
```
- 安装
```
cd nginx-1.9.5
./configure --prefix=/home/itczl/software/nginx --with-http_ssl_module  --enable-fpm --add-module=/home/itczl/software/nginx/module/echo-nginx-module [添加自定义模块]
make -j2
make install
```
- 启动
```
sudo /home/itczl/software/nginx/sbin/nginx -c /home/itczl/software/nginx/conf/nginx.conf
```
- 重启
```
sudo /home/itczl/software/nginx/sbin/nginx -c /home/itczl/software/nginx/conf/nginx.conf -s reload
```
- 停止
```
sudo  /home/itczl/software/nginx/sbin/nginx -c /home/itczl/software/nginx/conf/nginx.conf -s stop
```
- 增加对某个某块的支持
```
以http_ssl_module为例
查看原有的编译参数
    /home/itczl/software/nginx/sbin/nginx  -V
重新编译
    cd nginx-1.9.5
    ./configure 原有参数  --with-http_ssl_module
    make
    cp  /home/itczl/software/nginx/sbin/nginx  /home/itczl/software/nginx/sbin/nginx.bak
    cp /home/itczl/software/nginx-1.9.5/objs/nginx /home/itczl/software/nginx/sbin/nginx
```

## Nginx配置
- [Nginx官方配置示例](https://www.nginx.com/resources/wiki/start/topics/examples/full "Nginx官方配置示例")
- Nginx配置文件结构  
![Nginx配置文件结构](/nginx/nginx-config-structure.png, "Nginx配置文件结构")
- main模块
```
user itczl itczl;                   #定义Nginx运行的用户和用户组, default: nobody
worker_processes 2;                 #nginx进程数, 建议设置为等于CPU总核心数, default: 1
error_log log/error.log info;       #全局错误日志定义类型, [debug | info | notice | warn | error | crit]
pid var/nginx.pid;                  #进程pid文件
worker_rlimit_nofile 65535;         #一个nginx进程打开的最多文件描述符数目, 与ulimit -n的值保持一致. 
events {                            #工作模式与连接数上限
    use epoll;                      #参考事件模型, [kqueue | rtsig | epoll | /dev/poll | select | poll]
    worker_connections 65535;       #单个进程最大连接数, default: 1024
}
```
- http模块
```
http {
    include mime.types;                     #文件扩展名与文件类型映射表
    default_type application/octet-stream;  #默认文件类型
    server_names_hash_bucket_size 128;      #服务器名字的hash表大小
    client_header_buffer_size 32k;          #上传文件大小限制
    large_client_header_buffers 4 32k;      #设定请求缓
    client_max_body_size 80m;               #设定请求缓
    sendfile on;                            #开启高效文件传输模式, sendfile指令指定nginx是否调用sendfile函数来输出文件, 对于普通应用设为 on, 如果用来进行下载等应用磁盘IO重负载应用, 可设置为off, 以平衡磁盘与网络I/O处理速度, 降低系统的负载. 注意：如果图片显示不正常把这个改成off. 
    autoindex off;                          #开启目录列表访问, 合适下载服务器, 默认关闭. 
    tcp_nopush on;                          #防止网络阻塞
    tcp_nodelay on;                         #防止网络阻塞
    keepalive_timeout 60;                   #长连接超时时间, 单位是秒

    #fastcgi相关参数是为了改善网站的性能, 减少资源占用, 提高访问速度
    fastcgi_connect_timeout 300;            #指定连接到后端FastCGI的超时时间
    fastcgi_send_timeout 300;               #指定向FastCGI传送请求的超时时间
    fastcgi_read_timeout 300;               #指定接收FastCGI应答的超时时间
    fastcgi_buffer_size 64k;                #用于指定读取FastCGI应答第一部分(应答头)需要用多大的缓冲区
    fastcgi_buffers 4 64k;                  #指定本地需要用多少和多大的缓冲区来缓冲FastCGI的应答请求. 如果一个PHP脚本所产生的页面大小为256KB, 那么会为其分配4个64KB的缓冲区来缓存；如果页面大小大于256KB, 那么大于256KB的部分会缓存到fastcgi_temp指定的路径中, 但是这并不是好方法, 因为内存中的数据处理速度要快于硬盘. 一般这个值应该为站点中PHP脚本所产生的页面大小的中间值, 如果站点大部分脚本所产生的页面大小为256KB, 那么可以把这个值设置为“16 16k”、“4 64k”等. 
    fastcgi_busy_buffers_size 128k;         #默认值是fastcgi_buffers的两倍. 
    fastcgi_temp_file_write_size 128k;      #表示在写入缓存文件时使用多大的数据块, 默认值是fastcgi_buffers的两倍. 

    #gzip模块设置
    #gzip的压缩页面需要浏览器和服务器双方都支持, 实际上就是服务器端压缩, 传到浏览器后浏览器解压并解析
    gzip on;                #开启gzip压缩输出
    gzip_min_length 1k;     #最小压缩文件大小
    gzip_buffers 4 16k;     #压缩缓冲区, 设置系统获取几个单位的缓存用于存储gzip的压缩结果数据流
    gzip_http_version 1.0;  #压缩版本(默认1.1)
    gzip_comp_level 5;      #压缩等级(1-9), 等级越低, 压缩速度越快, 文件压缩比越小
    gzip_types text/plain application/x-javascript text/css application/xml; #压缩类型, 无论是否指定text/html类型总是会被压缩的
    gzip_vary on;           #加上http头信息'Vary: Accept-Encoding'给后端代理服务器识别是否启用gzip压缩

    #日志格式, 格式名字'main'可以自定义
    log_format  main  '$remote_addr - $remote_user [$time_local] "$request" $request_body '
        '^$status^ $body_bytes_sent "$http_referer" '
        '"$http_user_agent" "$http_x_forwarded_for" ^"$request_time"^ "^$upstream_response_time^" ';
}
```
- server模块
```
```
- location模块
```
```
