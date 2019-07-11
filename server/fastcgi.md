webserver[ 比如Nginx、Apache、lighttpd ]不支持对外部程序的直接调用或者解析, 所有的外部程序必须通过服务器支持的协议[ 比如http(golang)、fastcgi(php)、uwsgi(python) ]来通信. Nginx通过反向代理将请求发给对应的监听端口  

每个语言都有对应的和webserver通信的方式, 比如php一般通过fastcgi协议对应实现是php-fpm, Python采用wsgi协议对应的实现是uwsgi, go一般用http协议即直接由nginx proxy_pass过来

但是像lua这样的语言是通过nginx模块集成, 直接作为webserver的一部分来运行, 即openresty, 所以他提供的并发会更高, 因为他不需要通过中间协议来协调webserver和application之间的通信  

本文只讨论fastcgi协议

FastCGI接口在Linux下是socket(这个socket可以是文件socket, 也可以是ip socket), 在每个语言中都有对应的实现

比如golang中, 有net/http/cgi 和 net/http/fcgi两个包来实现  
* nginx.conf
```
  server {
      listen 80; 
      server_name  gocgi.com;
      access_log  logs/gocgi.access.log  main;
      root /x/go/study/src/itczl.com;
      index index.php;

      location / { 
          try_files $uri $uri/ /index.php?$query_string;
      }   

      # pass the request to FastCGI server listening on 127.0.0.1:9001
      location ~ /gocgi.* {
          fastcgi_pass 127.0.0.1:9001;
          include fastcgi_params;
          include fastcgi.conf;
      }   
  } 
```
* go program  
```
    // /x/go/study/src/itczl.com/itczl/main.go
    package main                                                                                                                                                                             

    import (
        "net"
        "net/http"
        "net/http/fcgi"
    )

    type FastCGIServer struct{}

    func (s FastCGIServer) ServeHTTP(resp http.ResponseWriter, req *http.Request) {
        resp.Write([]byte("Hello, Golang FastCGI"))
    }

    func main() {
        listener, _ := net.Listen("tcp", "127.0.0.1:9001")
        srv := new(FastCGIServer)
        fcgi.Serve(listener, srv)
    }
```
* 启动go服务  

* 访问  
curl -H "Host:gocgi.com" "http://17.17.17.17/gocgi"

* **但是**对于静态语言我们一般**不采取**fastcgi的方式, 而是直接通过**proxy_pass**将请求代理到后端服务**

对于php, 目前主流的方式是通过FastCGI [php-fpm] 来和http server通信,  虽然php本身也内置了一个web server [php -S] 但是官方不建议生成环境使用. 当然如果要用PHP开发非http协议的应用, 例如TCP长链接, 就得脱离现有的web架构使用PHP的socket服务器框架

* nginx.conf
```
  server {
      listen       80;
      charset utf-8;                                                                                                                                                                       
      
      server_name  phpcgi.com
      access_log  logs/itczl.access.log  main;        
      root /x/work/web;
      index index.php;                location / {
          try_files $uri $uri/ /index.php?$query_string;
      }   
      
      # pass the PHP scripts to FastCGI server listening on 127.0.0.1:9000
      location ~ \.php$ {
          fastcgi_pass   127.0.0.1:9000;
          fastcgi_index  index.php;
          fastcgi_param  SCRIPT_FILENAME  $document_root/$fastcgi_script_name;
          include        fastcgi_params;  
      }   
      
  }  
```
* php program
```
 // /x/work/web/index.php
 <?php
 echo "Hello PHP FastCGI"
```

* 配置php-fpm.conf  
```
 listen = 127.0.0.1:9000
```

* 启动php-fpm
```
  /x/software/php7.0/sbin/php-fpm /x/software/php7.0/etc/php-fpm.conf
```

* 访问  
  curl -H "Host:phpcgi.com" "http://17.17.17.17/phpcgi"
  
#### cgi

* Common Gateway Interface

* CGI 是Web服务器运行时外部程序的`规范接口`, 按CGI 编写的程序可以扩展服务器功能, 几乎所有服务器都支持CGI, 可用任何语言编写CGI

* 早期的webserver只处理html等静态文件, 但是随着技术的发展, 出现了像php等动态语言, webserver处理不了了怎么办呢？那就交给对应的解释器来处理, 比如php-fpm

* 交给php解释器处理很好, 但是php解释器如何与webserver进行通信呢? 为了解决不同的语言解释器(如php、python解释器)与webserver的通信, 于是出现了cgi协议. 只要你按照cgi协议去编写程序, 就能实现语言解释器与webwerver的通信. 如php-cgi程序

* 但是webserver每收到一个请求, 都会去fork一个cgi进程, 请求结束再kill掉这个进程. 这样有10000个请求就需要fork、kill php-cgi进程10000次, 所以无法管理cgi进程

  
传统CGI接口方式的主要缺点是性能很差, 因为每次HTTP服务器遇到动态程序时都需要重新启动脚本解析器来执行解析, 然后将结果返回给HTTP服务器. 这在处理高并发访问时几乎是不可用的, 另外传统的CGI接口方式安全性也很差, 现在已经很少使用了

#### fast-cgi

* 由于cgi会严重浪费资源, 所以就有cgi的改良版本fast-cgi

* FastCGI是一个可伸缩地、高速地在HTTP server和application间通信的接口. 多数流行的HTTP server都支持FastCGI，包括Apache、Nginx和lighttpd等. 同时FastCGI也被许多脚本语言支持, 其中就有PHP. FastCGI是从CGI发展改进而来的.
FastCGI接口方式采用C/S结构, 可以将HTTP服务器和脚本解析服务器分开, 同时在脚本解析服务器上启动一个或者多个脚本解析守护进程. 当HTTP服务器每次遇到动态程序时, 可以将其直接交付给FastCGI进程来执行, 然后将得到的结果返回给浏览器.
这种方式的优点：可以让HTTP服务器专一地处理静态请求或者将动态脚本服务器的结果返回给客户端, 这在很大程度上提高了整个应用系统的性能

* fast-cgi每次处理完请求后, 不会kill掉这个进程, 而是保留这个进程, 使这个进程可以一次处理多个请求. 这样每次就不用重新fork一个进程了, 大大提高了效率 

#### php-fpm

* 全称 PHP-Fastcgi Process Manager

* 是FastCGI 的实现, 并提供了进程管理的功能. 进程包含 master 进程和 worker 进程两种进程

* master 进程只有一个, 负责监听端口接收来自 Web Server 的请求, 而 worker 进程则一般有多个(具体数量根据实际需要配置php-fpm.conf中的 max_children), 每个进程内部都嵌入了一个 PHP 解释器, 是 PHP 代码真正执行的地方.

#### nginx 如何结合 php-fpm运行

* Nginx将请求转向后端php-fpm
```
  # nginx.conf
  location ~ .*\.php$ {
    fastcgi_pass   127.0.0.1:9000               # fastcgi进程监听的地址
    include fastcgi.conf;                       # 加载nginx的fastcgi模块
    include fastcgi_params;
  }
```

* php-fpm master进程监听指定的端口等待请求到来交给work去处理
```
  # php-fpm.conf
  listen = 127.0.0.1:9000
```
  
*  流程  
```
 www.itczl.com
        |
      Nginx
        |
路由到www.itczl.com/index.php
        |
加载nginx的fast-cgi模块
        |
fast-cgi监听127.0.0.1:9000地址
        |
www.itczl.com/index.php请求到达127.0.0.1:9000
        |
php-fpm 监听127.0.0.1:9000
        |
php-fpm 接收到请求，启用worker进程处理请求
        |
php-fpm 处理完请求，返回给nginx
        |
nginx将结果通过http返回给浏览器
```        
        
#### php-fpm.conf

* pm = static  
```
; Choose how the process manager will control the number of child processes.
; Possible Values:
;   static  - a fixed number (pm.max_children) of child processes;
;   dynamic - the number of child processes are set dynamically based on the 
;             following directives. With this process management, there will be
;             always at least 1 children.
;             pm.max_children      - the maximum number of children that can 
;                                    be alive at the same time.
;             pm.start_servers     - the number of children created on startup.
;             pm.min_spare_servers - the minimum number of children in 'idle'
;                                    state (waiting to process). If the number
;                                    of 'idle' processes is less than this
;                                    number then some children will be created.
;             pm.max_spare_servers - the maximum number of children in 'idle'
;                                    state (waiting to process). If the number
;                                    of 'idle' processes is greater than this
;                                    number then some children will be killed.
;  ondemand - no children are created at startup. Children will be forked when
;             new requests will connect. The following parameter are used:
;             pm.max_children           - the maximum number of children that
;                                         can be alive at the same time.
;             pm.process_idle_timeout   - The number of seconds after which
;                                         an idle process will be killed.
;pm = dynamic
```
  * 标识fpm子进程的产生模式  
  * 一般推荐用static, 优点是不用动态的判断负载情况, 提升性能, 缺点是多占用些系统内存资源

  
* pm.max_children = 256  
```
; The number of child processes to be created when pm is set to 'static' and the 
; maximum number of child processes when pm is set to 'dynamic' or 'ondemand'.
; This value sets the limit on the number of simultaneous requests that will be
; served. Equivalent to the ApacheMaxClients directive with mpm_prefork.
; Equivalent to the PHP_FCGI_CHILDREN environment variable in the original PHP
; CGI. The below defaults are based on a server without much resources. Don't
; forget to tweak pm.* to fit your needs.
; Note: Used when pm is set to 'static', 'dynamic' or 'ondemand'
; Note: This value is mandatory.
;pm.max_children = 5
```
  * php-fpm创建的worker进程数, 也是同时能处理的最大请求数
  * 假设max_children设置的较小, 比如5-10个, 那么php-cgi就会"很累", 处理速度也很慢, 等待的时间也较长
  * 如果长时间没有得到处理的请求就会出现504 Gateway Time-out这个错误, 而正在处理的很累的那几个php-cgi如果遇到了问题就会出现502 Bad gateway这个错误
  * 正常情况下每一个php-cgi所耗费的内存在20M左右, 256*20M = 5.12G

  
* pm.max_requests = 10000
```
; The number of requests each child process should execute before respawning.
; This can be useful to work around memory leaks in 3rd party libraries. For
; endless request processing specify '0'. Equivalent to PHP_FCGI_MAX_REQUESTS.
; Default Value: 0
;pm.max_requests = 500
```
  * 最大处理请求数是指一个php-fpm的worker进程在处理多少个请求后就终止掉, master进程会重新respawn一个新的
  * 这个配置的主要目的是避免php解释器或程序引用的第三方库造成的内存泄露
  * 当一个 PHP-CGI 进程处理的请求数累积到 max_requests 个后，自动重启该进程
  * 502是后端 PHP-FPM 不可用造成的, 间歇性的502一般认为是由于 PHP-FPM 进程重启造成的
  * 正是因为这个机制, 在高并发中, 经常导致 502 错误

  
* pm.start_servers = 2
```
; The number of child processes created on startup.
; Note: Used only when pm is set to 'dynamic'
; Default Value: min_spare_servers + (max_spare_servers - min_spare_servers) / 2
```

* pm.min_spare_servers = 1
```
; The desired minimum number of idle server processes.
; Note: Used only when pm is set to 'dynamic'
; Note: Mandatory when pm is set to 'dynamic'
```

* pm.max_spare_servers = 3
```
; The desired maximum number of idle server processes.
; Note: Used only when pm is set to 'dynamic'
; Note: Mandatory when pm is set to 'dynamic'
```

* pm.process_idle_timeout = 10s;
```
; The number of seconds after which an idle process will be killed.
; Note: Used only when pm is set to 'ondemand'
; Default Value: 10s
;pm.process_idle_timeout = 10s;
```

#### Nginx和PHP-FPM的通信方式

Nginx和PHP-FPM的进程间通信有两种方式, 一种是TCP, 一种是UNIX Domain Socket

* tcp 
IP加端口，可以跨服务器
则需要走到IP层, 对于非同一台服务器上, TCP Socket走的就更多了.
```
  php-fpm.conf:  listen = 127.0.0.1:9000
  nginx.conf:    fastcgi_pass 127.0.0.1:9000;
```

* uinx domain socket  
不经过网络，只能用于Nginx跟PHP-FPM都在同一服务器的场景  
这种通信方式是发生在系统内核里而不会在网络里传播. 
```
  php-fpm.conf:  listen = /x/software/php7.0/var/php-fpm.sock
  nginx.conf:    fastcgi_pass unix:/x/software/php7.0/var/php-fpm.sock;
```

UNIX Domain Socket和长连接都能避免频繁创建TCP短连接而导致TIME_WAIT连接过多的问题. 

socket API原本是为网络通讯设计的，但后来在socket的框架上发展出一种IPC机制，就是UNIX Domain Socket。虽然网络socket也可用于同一台主机的进程间通讯（通过loopback地址127.0.0.1），但是UNIX Domain Socket用于IPC更有效率：不需要经过网络协议栈，不需要打包拆包、计算校验和、维护序号和应答等，只是将应用层数据从一个进程拷贝到另一个进程。这是因为，IPC机制本质上是可靠的通讯，而网络协议是为不可靠的通讯设计的。UNIX Domain Socket也提供面向流和面向数据包两种API接口，类似于TCP和UDP，但是面向消息的UNIX Domain Socket也是可靠的，消息既不会丢失也不会顺序错乱。

#### php-cgi和php-fpm的关系

* php-fpm跟php-cgi没有任何关系

* php-cgi是cgi协议的实现, 它本身没法用来管理cgi进程

* php-fpm是fastcgi进程管理器, 是一个独立的SAPI, 其管理的不是php-cgi, php-fpm内置php解释器, php-fpm的子进程是自己fork出来的, 并不会调用php-cgi, 你把系统中的php-cgi删了也不会影响到php-fpm服务的正常运行.
