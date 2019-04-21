# Zookeeper的安装、配置、使用
## 参考
[安装步骤](https://zookeeper.apache.org/doc/trunk/zookeeperStarted.html)  
[配置选项说明](http://zookeeper.apache.org/doc/current/zookeeperAdmin.html#sc_configuration[配置选项说明])  
[下载地址](http://mirror.bit.edu.cn/apache/zookeeper/ "下载地址")

## 安装
下载二进制安装包解压即用
- wget http://mirror.bit.edu.cn/apache/zookeeper/zookeeper-3.4.7.tar.gz
- tar zxvf zookeeper-3.4.7.tar.gz

##配置
- 生成配置文件
```
  mv conf/zoo_example.cfg  conf/zoo_standlone.cfg  
  修改dataDir目录: dataDir=/home/itczl/software/zookeeper/data
```
- 启动  
```
  ./bin/zkServer.sh  start  ./conf/zoo_standalone.cfg
```
- 检查是否启动成功  
```
  ps -Fp `cat /home/itczl/software/zookeeper/data/zookeeper_server.pid`  
  jps, 其中QuorumPeerMain就是zookeeper进程  
  ./bin/zkServer.sh status ./conf/zoo_standalone.cfg
```
- 连接zookeeper  
```
  ./bin/zkCli.sh -server 127.0.0.1:2181  [java]    //因为zookeeper有java编译和c编译的
```
- 基本操作
  - 帮助  
  ```
    help
  ```
  - 列出
  ```
    ls /
  ```
  - 创建znode  
  ```
    create /zk_test test_data   //创建zk_test并关联数据test_data  
    可以用ls / 查看是否创建成功
  ```
  - 获取
  ```
    get /zk_test                //查看zk_test是否成功关联test_data
  ```
  - 更改关联  
  ```
    set  /zk_test  my_data      //更改znode关联到my_data
  ```
  - 删除znode  
  ```  
    delete /zk_test
  ```
  - 退出  
  ```
    quit
  ```
- 停止zookeeper  
```
  ./bin/zkServer.sh stop ./conf/zoo_standalone.cfg
```
- 注意  
  如果不指定配置文件, 默认使用的是./conf/zoo.cfg, 所以如果不使用默认的配置文件必须在命令后边加上./conf/zoo_xxx.cfg

## zookeeper的3种工作模式
- 单机模式standalone  
  如上所示.
- 集群模式replicated  
  - 在单机模式的基础上增加
  ```
    server.1=host1.2888:3888
    server.2=host2.2888:3888
    server.3=host3.2888:3888
  ```
  其中hostX为服务器的host, 2888端口是zookeeper服务相互通信使用的, 3888端口是zookeeper服务选举leader使用的, 也就是leader的端口号.  
  注意: 需要配置/etc/hosts文件
  - 'server.' 后面的1,2和3需要做些配置, 在每台服务器的dataDir目录下, 创建myid文件, 里面包含对应的1, 2和3.  
     在集群模式下, 需要通过myid来确定是哪一个server
  - 然后分别启动3台服务器
  ```
  ./bin/zkServer.sh start  ./conf/zoo_replicated.cfg
  ```
  - 由于ZooKeeper集群启动的时候, 每个结点都试图去连接集群中的其它结点, 先启动的肯定连不上后面还没启动的, 所以先产生的异常日志是可以忽略的.当集群在选出一个Leader后, 最后就会稳定了
  - 都以 2n+1 = m 搭建, 这也就是说允许最多n台服务器的失效, 其中m为服务器个数. 一般3台起, 3台和4台效果一样. 因此, 偶数w个服务器和奇数w-1个服务器允许坏掉的服务器个数是一样的, 为了省服务器最好用奇数个.
- 伪集群模式
  - 目录格式  
    ![伪集群目录格式](/zookeeper/path-format.png "伪集群目录格式")

  - 把zookeeper-3.4.7里边的bin、conf分别拷贝到server1、server2、server3中, 分别创建data目录, 新建myid. 启动1、2、3  
  - 配置hosts文件  
  ```
    10.172.86.152  zoo1
    10.172.86.152  zoo2
    10.172.86.152  zoo3
  ```
  - 在单机模式的基础上增加
  ```
    dataDir=/home/itczl/software/zookeeper/server[1-3]/data
    clientPort=218[1-3]
    server.1=zoo1:2887:3887
    server.2=zoo2:2888:3888
    server.3=zoo3:2889:3889
  ```
  - 每个配置文件的clientPort必须不一样, 2181、2182、2183
  - 在每个serverX的dataDir目录下, 创建myid文件, 里面包含对应的1, 2和3
  - 然后分别到每个目录下启动服务器: ./bin/zkServer.sh start  ./conf/zoo_pseudo.cfg
  - 可以看出这种伪集群模式的冗余度比较高

