# Kafka的安装、配置、使用  
## 相关链接
- [官方参考文档](http://kafka.apache.org/documentation.html#quickstart "kafka安装教程")  
- [下载地址](http://kafka.apache.org/downloads.html "下载地址")  

## 安装
- 直接下载二进制安装包, 解压后即可用
- wget http://mirrors.cnnic.cn/apache/kafka/0.9.0.0/kafka_2.11-0.9.0.0.tgz
- tar -zxvf kafka_2.11-0.9.0.tgz

## config目录简介
- server.properties是针对kafka-server-start.sh服务的
- producer.properties是针对kafka-console-producer.sh服务的[just for test]. 生产环境中producer需要程序调用api来配置
- consumer.properties是针对kafka-console-consumer.sh服务的[just for test]. 生产环境中client需要通过api来配置
- zookeeper.properties是针对zookeeper-server-start.sh服务的[just for test]. 生产环境中单独启动zookeeper集群, 通过host:port连接. 默认使用的是Kafka自己打包的zookeeper

## 3种使用模式
### Single node – single broker(单节点单broker)
- 启动[zookeeper](https://itczl.com)
```
    先去zookeeper目录下启动zookeeper的单个实例
    ./bin/zkServer.sh start ./conf/zoo_standalone.cfg
    jps会显示QuorumPeerMain
```
- 启动kafka
```
    ./bin/kafka-server-start.sh config_ss/server.properties  &  
    jps会显示Kafka
```
- 创建topic
```
    ./bin/kafka-topics.sh --create --zookeeper itczl:2181 --replication-factor 1 --partitions 1 --topic testSS  
    注意kafka的topic命名最好不要使用period ’.’ 和 underline ’_’。如：test_ss -> testSS  
```
- 列出topic  
```   
    ./bin/kafka-topics.sh --list --zookeeper itczl:2181
```
- 启动producer  
```
    ./bin/kafka-console-producer.sh --broker-list  itczl:9092 --topic testSS
```
- 启动consumer  
```    
    ./bin/kafka-console-consumer.sh --zookeeper itczl:2181 --topic testSS --from-beginning
```
- 描述top  
```
    ./bin/kafka-topics.sh --zookeeper itczl:2181 --topic tesSS --describe
```
- 停止kafka  
```
    ./bin/kafka-server-stop.sh  config_alone/server.properties
```
- 停止zookeeper  
```
    ./bin/zkServer.sh stop ./conf/zoo_standalone.cfg
```

### Single node – multiple broker(单节点多broker) - 以3个broker为例
- 首先copy3个server.properties配置文件, 并修改如下  
    - server-1
    ```
        host.name=broker1
        brokerid=1
        port=9091
        log.dirs=/home/itczl/software/kafka/log/kafka-sm-logs/kafka-sm1-logs
        num.partitions=3
        zookeeper.connect=zoo1:2181,zoo2:2182,zoo3:2183
    ```
    - server-2
    ```
        host.name=broker2
        brokerid=2
        port=9092
        log.dirs=/home/itczl/software/kafka/log/kafka-sm-logs/kafka-sm2-logs
        num.partitions=3
        zookeeper.connect=zoo1:2181,zoo2:2182,zoo3:2183
    ```
    - server-3
    ```
        host.name=broker3
        brokerid=3
        port=9093
        log.dirs=/home/itczl/software/kafka/log/kafka-sm-logs/kafka-sm3-logs
        num.partitions=3
        zookeeper.connect=zoo1:2181,zoo2:2182,zoo3:2183
    ```
    
- 启动zookeeper
    ```
    先去zookeeper目录下启动zookeeper的单个实例
    ./bin/zkServer.sh start ./conf/zoo_standalone.cfg
    ```
    
- 启动broker
    ```
    ./bin/kafka-server-start.sh  -daemon ./config_sm/server1.properties
    ./bin/kafka-server-start.sh  -daemon ./config_sm/server2.properties
    ./bin/kafka-server-start.sh  -daemon ./config_sm/server3.properties
    ```
    
- 创建topic
    ```  
    ./bin/kafka-topics.sh --create --zookeeper zoo1:2181,zoo2:2182,zoo3:2183  --replication-factor 2 --partitions 3 --topic testSM
    ```
    
- 列出topic
    ```
    ./bin/kafka-topics.sh --list --zookeeper zoo1:2181,zoo2:2182,zoo3:2183
    ```
    
- 启动producer  
    ```
    ./bin/kafka-console-producer.sh --broker-list  broker1:9091 broker2:9092 broker3:9093  --topic testSM
    ```
    
- 启动consumer  
    ```
    ./bin/kafka-console-consumer.sh --zookeeper  zoo1:2181,zoo2:2182,zoo3:2183 --topic testSM --from-beginning
    ```
    
- hosts配置
    ```
    ##for zookeepers
    10.172.86.152  zoo       #用于单个实例运行,不能配外网

    10.172.86.152  zoo1      #用于集群
    10.172.86.152  zoo2
    10.172.86.152  zoo3

    ##for kafka brokers
    10.172.86.152  broker    #用于单个实例运行

    10.172.86.152  broker1   #用于集群
    10.172.86.152  broker2
    10.172.86.152  broker3
    ```
    
### Multiple node – multiple broker(生产环境)
- 其实和sm一样，只是端口和目录没必要设置的不同
- log.dirs对应目录下的meta.properties文件中的broker.id必须和server.properties中的broker.id一直，否则会报错。如果之前存在这个meta.properties文件就必须修改为一直(避免麻烦启动时也可以直接删除这个文件，重新开始)
- 分别启动：./bin/kafka-server-start.sh ./config/server.properties &

