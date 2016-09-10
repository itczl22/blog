# 高性能web服务器Nginx
## Nginx简介
- Nginx  
Nginx是一个网页服务器, 它能反向代理HTTP, HTTPS, SMTP, POP3, IMAP的协议链接, 以及一个负载均衡器和一个HTTP缓存.
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

