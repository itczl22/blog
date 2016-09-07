# redis及nutcracker的安装、部署
## 参考
[官方网址](http://redis.io/topics/introduction)

## redis安装
- wget http://download.redis.io/releases/redis-3.0.7.tar.gz
- tar zxvf redis-3.0.7.tar.gz
- cd redis-3.0.7
- make
- mkdir -p ../redis/bin  ../redis/conf ../redis/var
- copy reds-3.0.7下边的可执行文件到redid/bin
- copy reds-3.0.7下边的redid.conf到redid/conf
- 删除redis-3.0.7目录

## redis部署
- 配置redis
    - 修改如下对应内容
```
  daemonize yes                             [以守护进程的方式启动]
  port 6379                                 [端口]
  bind  10.172.86.152                       [绑定到内网]
  dir /home/itczl/software/redis-3.0.7/data [数据存储路径]
  dbfilename dump.rdb                       [持久化数据的文件名]
  save 300 10                               [执行持久化数据的条件 每300s且有10个key改变]
  unixsocket /home/itczl/software/redis-3.0.7/var/redis.sock    [本地socket]
  pidfile /home/itczl/software/redis-3.0.7/var/redis.pid        [pid路径]
  logfile /home/itczl/software/redis-3.0.7/var/redis.log        [日志路径]
```
    - 注
        - 'save 300 10' means  after 300 sec (5 min) if at least 10 keys changed 就执行一次持久化数据操作.
        - 添加这个配置他会自动在dir配置的目录中生成以dbfilename配置命名的.rdb文件, 这样redis重启时它会自动读取这里边的数据, 可以放置redis挂了之后数据丢失.
        - 如果没有配置save, 你也可以登录redis执行save命令来生成该文件.
        - 如果同一台redis服务器启动多个端口必须修改上边对应得配置避免冲突.
        - 如果bind ip那么只有到改网卡的请求会被响应. 一般生产环境中需要绑定内网网卡, 避免外网访问. 如果配置文件不bind, 也可以在启动redis时绑定.
        - 对于redis cluster 同理批量部署其他redis服务器

## 操作redis服务
- 启动redis服务
```
sudo  /home/itczl/software/redis-3.0.7/bin/redis-server  /home/itczl/software/redis-3.0.7/conf/redis.conf [--bind 10.172.86.152]
```
- 查看redis服务时候启动成功
```
ps -FC redis-server
```
- 连接redis
```
redis-cli -h 10.172.86.152 -p 6379
```
- 关闭redis服务器：
```
sudo  /home/itczl/software/redis-3.0.7/bin/redis-cli -h 10.172.86.152 -p 6379 shutdown
或者直接kill
```

## nutcracker安装部署
- 安装nutcracker
```
     下载: https://github.com/twitter/twemproxy
     aclocal       ->aclocal.m4
     autoconf      ->用configure.ac和aclocal.m4生成configure
     autoheader    ->include/config.h.in文件
     mkdir config  ->需要手动创建
     cp /usr/share/libtool/config/ltmain.sh config     手动去拷贝
     automake  —add-missing     ->将makefile.am转换成makefile.in文件, —add-missing参数会将找不到的文件从/usr/share/autoconf去拷贝
     ./configure --prefix=/home/itczl/software/twemproxy-0.4.1   ->将makefile.in转换成Makefile
     make
     make install
```
