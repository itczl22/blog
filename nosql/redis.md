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
    - 拷贝redis.conf到redis_6800.conf, 修改如下对应内容
```
  daemonize yes                             [以守护进程的方式启动]
  port 6800                                 [端口]
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
sudo  /home/itczl/software/redis-3.0.7/bin/redis-server  /home/itczl/software/redis-3.0.7/conf/redis_6800.conf [--bind 10.172.86.152]
```
- 查看redis服务时候启动成功
```
ps -FC redis-server
```
- 连接redis
```
redis-cli -h 10.172.86.152 -p 6800
```
- 关闭redis服务器：
```
sudo  /home/itczl/software/redis-3.0.7/bin/redis-cli -h 10.172.86.152 -p 6800 shutdown
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
- 配置nutcracker
    - 拷贝nutcracker.yml到nutcracker_redis_6800.yml
```
business_strategy_center:
  listen: 10.172.86.152:6802     // 修改为本机ip
  hash: fnv1a_64                 // key值的hash算法
  distribution: ketama           // 存在ketama、modula和random 3种可选的配置
  auto_eject_hosts: false        // 是一个boolean值, 用于控制twemproxy是否应该根据server的连接状态重建群集
  redis: true                    // 是一个boolean值, 用来识别到服务器的通讯协议是redis还是memcached
  server_retry_timeout: 30000    // 单位是毫秒, 控制服务器连接的时间间隔, 在auto_eject_host被设置为true的时候产生作用
  server_failure_limit: 1        // 控制连接服务器的次数, 在auto_eject_hosts被设置为true的时候产生作用
  timeout: 10                    // 单位是毫秒, 是连接到server的超时值
  preconnect: true               // 是一个boolean值, 指示twemproxy是否应该预连接pool中的server
  servers:                       // 被代理的服务器
   - 172.16.38.75:6800:1 server1 // 被代理redis服务器ip和端口
   - 172.16.38.76:6800:1 server2
   - 172.16.38.77:6800:1 server3
   - 172.16.38.78:6800:1 server4
   - 172.16.88.226:6800:1 server5
   - 172.16.88.230:6800:1 server6
   - 172.16.89.132:6800:1 server7
   - 172.16.89.133:6800:1 server8
```
    - 注
        - 值和前边的冒号必须有空格
        - 对于nutcracker集群的其他机器做相同配置, 只需修改ip
- 启动nutcracker
```
/home/itczl/software/twemproxy-0.4.1/sbin/nutcracker -c /home/itczl/software/twemproxy-0.4.1/conf/nutcracker_redis_6800.yml -p /home/itczl/software/twemproxy-0.4.1/var/nutcracker_redis-6800.pid -o /home/itczl/software/twemproxy-0.4.1/nutcracker_redis-6800.log -d -s 16800 -a 172.16.89.128 -m 512
```

## redis部署示例 master-slave
- master配置参见上边redis服务器配置
- slave配置和服务器一样, 外加'slaveof 10.172.86.152 6800', 表示从属与这台master服务器
- 注意
    - 适用于大量的读取, 如用户访问的时候需要读取数据但是并不涉及到写操作, 写操作是由后台提前写进去的, 比如更改配置等等.
    - slave服务器只能读不能写, 写只能往master里边写, 其他slave会自动从master同步数据, 读的时候可以从3个代理里边随机一台去读, 而代理又会从这8台服务器去读.

