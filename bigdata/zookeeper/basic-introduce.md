# Zookeeper基础知识介绍
## 参考文章
[Zookeeper官网](https://cwiki.apache.org/confluence/display/ZOOKEEPER/ProjectDescription "zookeeper官方网站")

## 简介
- ZooKeeper is a centralized service for maintaining configuration information, naming, providing distributed synchronization, and providing group services
- ZooKeeper allows distributed processes to coordinate with each other through a shared hierarchical name space of data registers (we call these registers znodes), much like a file system

## 特点
- The main differences between ZooKeeper and standard file systems are that every znode can have data associated with it
- znodes are limited to the amount of data that they can have. ZooKeeper was designed to store coordination data: status information, configuration, location information, etc.
- ZooKeeper has a built-in sanity check of 1M, to prevent it from being used as a large data store

## 架构图
![Zookeeper架构图](/zookeeper/zookeeper-architecture.png "Zookeeper架构图")
- 所有的server必须都相互知道，只要大部分server是有效的那么整个service就是有效的
- 所有的server都是replicated，为了保证高吞吐量和低延时，这些server在内存中维护一个image of the data tree along with a transaction logs and snapshots. 因为内存是有限的，所以内存成了znode只能存储少量数据的further reason
- Clients must also know the list of servers. The clients create a handle to the ZooKeeper service using this list of servers.
- Clients only connect to a single ZooKeeper server. The client maintains a TCP connection through which it sends requests, gets responses, gets watch events, and sends heartbeats. If the TCP connection to the server breaks, the client will connect to a different server. When a client first connects to the ZooKeeper service, the first ZooKeeper server will setup a session for the client. If the client needs to connect to another server, this session will get reestablished with the new server.
- read request是由client所连接的那台Zookeeper server进行处理; 如果一个read request注册了watch在znode上，那么这个watch也只跟踪该Zookeeper server. 但是write request在响应产生之前必须转发到其他Zookeeper servers并达成共识. sync request也需要转发到其他server，但不需要go through consensus.
- 因此read request随着Zookeeper server的增加吞吐量在增加，而write request则随着server的增加吞吐量反而在下降

## Znode
- Every znode in ZooKeeper's name space is identified by a path.
- Every znode has a parent whose path is a prefix of the znode with one less element; the exception to this rule is root ("/") which has no parent.
- Like standard file systems, a znode cannot be deleted if it has any children.

## leader的选举
- 主要为了避免活锁，因为分布式里经常遇到修改同一个资源的情况，Zookeeper为了避免这种情况的出现把所有决策性的操作都交给leader来处理. 

## Zookeeper的数据结构
![zookeeper的数据结构](/zookeeper/zookeeper-ds.png "zookeeper的数据结构")  
     每一个目录节点都是一个znode，比如Server1、SubApp1

## 用途
Zookeeper 从设计模式角度来看是一个基于观察者模式设计的分布式服务管理框架, 它负责存储和管理大家都关心的数据, 然后接受观察者的注册一旦这些数据的状态发生变化, Zookeeper 就将负责通知已经在 Zookeeper上注册的那些观察者做出相应的反应, 从而实现集群中类似 Master/Slave 管理模式  
它主要是用来解决分布式应用中经常遇到的一些数据管理问题, 如：统一命名服务、配置同步、集群管理、分布式同步(共享锁、队列管理)
- 统一命名服务(name service)  
分布式应用中，通常需要有一套完整的命名规则, 既能够产生唯一的名称又便于人识别和记住, 树形的名称结构是一个有层次的目录结构, 且不会重复, Zookeeper采用树形结构 将有层次的目录结构关联到一定资源上. 如：create /zk_test test_data  创建zk_test并关联数据test_data
- 配置管理(configuration management)  
配置的管理在分布式应用环境中很常见，例如同一个应用系统需要多台 Server 运行, 但是它们运行的应用系统的某些配置项是相同的, 如果要修改这些相同的配置项, 那么就必须同时修改每台运行这个应用系统的 Server, 这样非常麻烦而且容易出错.  
像这样的配置信息完全可以交给 Zookeeper 来管理, 将配置信息保存在 Zookeeper 的某个目录节点中, 然后将所有需要修改配置的机器加入监控, 一旦配置信息发生变化, 每台应用机器就会收到 Zookeeper 的通知, 然后从 Zookeeper 获取新的配置信息应用到系统中.  
![Zookeeper配置管理](/zookeeper/config-manage.png "Zookeeper配置管理")  
- 集群管理(group management)
Zookeeper 能够很容易的实现集群管理的功能，如有多台 Server 组成一个服务集群, 那么必须要一个 leader 知道当前集群中每台机器的服务状态, 一旦有机器不能提供服务，集群中其它集群必须知道从而做出调整重新分配服务策略. 同样当增加集群的服务能力时， 就会增加一台或多台 Server，同样也必须让leader知道. 这就涉及到leader的选举.  
![Zookeeper集群管理](/zookeeper/group-manage.png "Zookeeper集群管理")  
- 分布式同步  
共享锁  
队列管理

