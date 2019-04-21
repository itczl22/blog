# kafka基础的简单介绍
## 参考文章
- [kafka官网](http://kafka.apache.org/documentation.html#uses "kafka官方文档")

## Kafka
Kafka是2010年12月份开源的项目，采用scala语言编写，使用了多种效率优化机制，整体架构比较新颖（push/pull）更适合异构集群  
设计目标
- 数据在磁盘上的存取代价为O(1)
- 高吞吐率，在普通的服务器上每秒也能处理几十万条消息
- 分布式架构，能够对消息分区
- 支持将数据并行的加载到hadoop

## 架构
Kafka实际上是一个消息发布订阅系统。producer向某个topic发布消息，而consumer订阅某个topic的消息，进而一旦有新的关于某个topic的消息broker会传递给订阅它的所有consumer。
在kafka中消息是按topic组织的而每个topic又会分为多个partition，这样便于管理数据和进行负载均衡。
多个consumer可以隶属同一个group这样可以避免消费重复的数据。同时，它也使用了zookeeper进行负载均衡

架构图  
![kafka结构图](/kafka/kafka-architecture.png "kafka架构图")

## 概念介绍
broker
- Kafka集群包含一个或多个服务器，这种服务器被称为broker，就是部署Kafka服务的集群
- kafka cluster会存储所有published的message直到达到配置的过期日期，无论是consumed还是非consumed

producer
- 负责发布消息到Kafka broker，可以是前端机产生的日志等。一般是指往kafka推送数据的程序，kafka提供了一个用于测试的producer: kafka-console-producer.sh
- producer将会和Topic下所有partition leader保持socket连接; 消息由producer直接通过socket发送到broker，中间不会经过任何"路由层"
- 事实上，消息被路由到哪个partition上，有producer客户端决定。比如可以采用"random"、"key-hash"、"轮询"等
- 如果一个topic中有多个partitions，那么在producer端实现"消息均衡分发"是必要的。比如利用zookeeper，注册leader用来listen

consumer
- 消息消费者是向Kafka broker读取消息的客户端，Kafka提供了一个默认的客户端：kafka-console-consumer.sh
- offset(consumer在log中的位置)是由consumer自己控制的，所以可以任意顺序读取，比如读取已经读取的数据。好比文件指针可以人为的随便移动
  
topic
- 每条发布到kafka集群的消息都有一个类别，这个类别被称为topic
- 物理上不同topic的消息分开存储，逻辑上一个topic的消息虽然保存于一个或多个broker上但用户只需指定消息的topic即可生产或消费数据而不必关心数据存于何处
- 对于同一个topic，相同group的consumer不会重复消费同一个message，相当于queue
- 不同group的consumer，他们对同一个topic的message的消费是相互独立的，自己各持一个offset

partition
- partition是物理上的概念，每个topic包含一个或多个partition，作用是提高kafka的吞吐率
- 如果一个topic对应一个文件即一个partition，那这个文件所在的机器I/O将会成为这个topic的性能瓶颈
- 如果一个topic的名称为"my_topic"，它有2个partitions，那么日志将会保存在my_topic_0和my_topic_1两个目录中
- 有了partition后，不同的消息可以并行写入不同broker的不同Partition里，极大的提高了吞吐率。因此，同一个topic的多个partition存储不同的数据
- 一般partition的个数大于等于broker的个数，并且所有partition的leader均匀的分布在leader上
- 物理上每个partition如果超过文件指定大小就会被分为多个segment文件，由log.segment.bytes决定,默认1G，以该文件起始offset命名
- 可以在$KAFKA_HOME/config/server.properties中通过配置项num.partitions来指定新建Topic的默认Partition数量，也可在创建Topic时通过参数指定，同时也可以在Topic创建之后通过Kafka提供的工具修改

consumer group
- 每个Consumer属于一个特定的Consumer Group,可为每个Consumer指定group name，若不指定group name则属于默认的group
- 同一Topic的一条消息只能被同一个Consumer Group内的一个Consumer消费，但多个Consumer Group可同时消费这一消息。

replication
- 从kafka0.8 起支持partition级别的replication，每一个partition都有一个唯一的leader
- 所有的读写都是在leader上完成的，follower都是从leader复制数据，如果leader死掉，会从剩下的followers里边选举一个新的leader
- 如果followers复制数据落后就会被从’in sync’的node list剔除。落后多久才被踢出由 replica.lag.max.messages＝400  配置决定
- 当所有的follower都将一条消息保存成功,此消息才被认为是"committed"，那么此时consumer才能消费它

注意
- 对于一个topic，同一个group中不能有多于partitions个数的consumer同时消费，否则将意味着某些consumer将无法得到消息
- 其中consumer和producer是client，kafka默认提供了两个简单的脚本作为客户端用于测试安装，其配置文件见下
- 官方提供了java版的API。go语言的客户端(API)：https://github.com/Shopify/sarama

特点
- 显式分布式，即所有的producer、broker和consumer都会有多个，均为分布式的
- 所有broker和consumer都会在zookeeper中进行注册，且zookeeper会保存他们的一些元数据信息
如果某个broker和consumer发生了变化，所有其他的broker和consumer都会得到通知
- Kafka通过Zookeeper管理集群配置，选举leader，以及在Consumer Group发生变化时进行rebalance
- Producer使用push模式将消息发布到broker，Consumer使用pull模式从broker订阅并消费消息
- Topic在逻辑上可以被认为是一个queue，每条消费都必须指定它的Topic，可以简单理解为必须指明把这条消息放进哪个queue里
- 为了使得Kafka的吞吐率可以线性提高，物理上把Topic分成一个或多个Partition，每个Partition在物理上对应一个文件夹，
该文件夹下存储这个Partition的所有消息和索引文件。若创建topic1和topic2两个topic，且分别有13个和19个分区，则整个集群上会相应会生成共32个文件夹
- Producer发送消息到broker时，会根据Paritition机制选择将其存储到哪一个Partition。如果Partition机制设置合理，所有消息可以均匀分布到不同的Partition里，这样就实现了负载均衡

关键配置项的含义
- broker  
![broker主要配置说明](/kafka/broker-config.png "broker主要配置说明")
- consumer  
![consumer主要配置说明](/kafka/consumer-config.png "consumer主要配置说明")
- producer  
![producer主要配置说明](/kafka/producer-config.png "producer主要配置说明")  
这两个配置是对kafka提供的默认的consumer和producer客户端起作用，如果你是自己定义这两个客户端就需要调用api来指定

