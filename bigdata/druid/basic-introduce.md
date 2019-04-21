# Druid基础的简单介绍
## 参考文章
- [Druid官网介绍](http://static.druid.io/docs/druid.pdf "druid官方介绍")

## Druid
- Druid is an open source data store designed for OLAP queries on event data.
- Druid is a system built to allow fast ("real-time") access to large sets of seldom-changing data.
- Druid is good fit for products that require real-time data ingestion of a single, large data stream.

## Druid架构
Druid is architected as a grouping of systems each with a distinct role and together they form a working system.
![Druid架构图](/druid/druid-arch.png "Druid架构图")

## Realtime
- realtime的运行机制
    - Realtime nodes encapsulate the functionality to ingest and query event streams
    - The nodes announce their online state and the data they serve in Zookeeper.
    - Events indexed via these nodes are immediately available for querying. 而historical是download完才可以query.
    - Realtime nodes maintain an in-memory index buffer for all incoming events. These indexes随着数据的ingest在递增, and the indexes are also directly queryable.
    - Druid behaves as a row store for queries on events that exist in this JVM heap-based buffer. To avoid heap overflow problems, realtime nodes persist their in-memory indexes to disk either periodically or after some maximum row limit is reached. 可配置的, 如10min. This persist process converts data stored in the in-memory buffer to a column oriented storage format
    - real-time会周期性的执行一个background task that searches for all locally persisted indexes. The task merges these indexes together and builds an immutable block of data that contains all the events that have been ingested by a realtime node for some span of time. We refer to this block of data as a “segment”.
    - During the handoff stage, a real-time node uploads this segment to a permanent backup storage,也就是存到deep storage.
    - ingest ->persist ->merge ->handoff stage are fluid; there is no data loss during any of the processes.

![Realtime运行流程图](/druid/real-time.png "Realtime运行流程图")
- 可用性和扩展性
    - Realtime nodes are a consumer of data and require a corresponding producer to provide the data stream.
    - Commonly, for data durability purposes, a message bus such as Kafka sits between the producer and the realtime node
    - The purpose of the message bus(kafka) is two-fold：
        - First purpose of the message bus is to  act as a buffer for incoming events    
        - Second purpose of the message bus is to act as a single endpoint from which multiple realtime nodes can read events. 
    - for second purpose: 
        - Multiple real-time nodes can ingest the same set of events from the bus, creating a replication of events. 有时候realtime死掉但是数据还没有persist到disk，就会造成丢失。这是其一
        - A single ingestion endpoint also allows for data streams to be partitioned such that multiple realtime nodes    
        - Each realtime nodes ingest a portion of a stream. This allows additional realtime nodes to be seamlessly added. 这个是主要作用，通过增加realtime来扩展处理能力。这是其二

## Historal
- Historical nodes encapsulate the functionality to load and serve the immutable blocks of data (segments)  which is created by real-time nodes.
- In many real-world workflows, most of the data loaded in a Druid cluster is immutable and hence, historical nodes are typically the main workers of a Druid cluster.
- historical会从deep storage中下载数据，在这之前他会检查本地缓存，没有才去下载.
- 下载segment完成后, the segment is announced in Zookeeper. At this point, the segment is queryable.
- availability
    - Historical nodes depend on Zookeeper for segment load and unload instructions.
    - If zookeeper become unavailable, historical nodes are no longer able to serve new data or drop outdated data.
    - However, zookeeper outages do not impact current data availability on historical nodes.
- History 架构图  
![History 架构图](/druid/historical.png " History 架构图")

## Broker
- Broker nodes act as query routers to historical and realtime nodes.
- Broker nodes understand the metadata published in Zookeeper about what segments are queryable and where those segments are located.
- Broker nodes route incoming queries such that the queries hit the right historical or real-time nodes.- Broker nodes merge partial results from historical and realtime nodes before returning a final consolidated result
- Caching
    - The cache can use local heap memory or an external distributed key/value store such as Memcached, 默认是本地堆内存    
    - Each time a broker node receives a query, it first maps the query to a set of segments. 有缓存就没必要再计算了    
    - 如果没有broker node will forward the query to the correct historical and realtime nodes, 然后再缓存
    - Realtime data is never cached. 因此realtime的query都被定向到realtime node。因为realtime数据一直变, 缓存没价值
    - 如果historical节点都坏了, 还可以从缓存中读取.
- Availability
    - In the event of a total Zookeeper outage, data is still queryable.
    - If broker nodes are unable to communicate to Zookeeper, they use their last known view of the cluster and continue to forward queries to real-time and historical nodes. 这样可以在我们确诊zookeeper出错之前继续提供查询服务.

## Coordinator
- Druid coordinator nodes are primarily in charge of data management and distribution on historical nodes.
- Coordinator nodes undergo a leader-election process that determines a single node that runs the coordinator functionality.
- The remaining coordinator nodes act as redundant backups. 
- Coordinator nodes maintain a Zookeeper connection for current cluster information.
- Coordinator nodes also maintain a connection to MySQL database that contains additional operational parameters and configurations.
- Replication
    - Coordinator nodes may tell different historical nodes to load a copy of the same segment.
    - Replicated segments are treated the same as the originals and follow the same load distribution algorithm.
- Availability
    - Druid coordinator nodes have Zookeeper and MySQL as external dependencies。
    - Coordinator nodes rely on Zookeeper to determine what historical nodes already exist in the cluster.
    - Druid uses MySQL to store operational management information and segment metadata information about what segments should exist in the cluster.
- Rules
    - Rules govern how historical segments are loaded and dropped from the cluster.
    - Rules indicate how segments should be assigned to different historical node tiers and how many replicates of a segment should exist in each tier.
    - Rules may also indicate when segments should be dropped entirely from the cluster. Rules are usually set for a period of time.
    - The coordinator nodes load a set of rules from a rule table in the MySQL database.

## External Dependencies
- Zookeeper    
    - A running ZooKeeper cluster for cluster service ‘discovery and maintenance of current data topology’.
- Mysql -- metadata
    - A metadata storage instance for maintenance of metadata about the data segments that should be served by the system. 默认用的是derby, 还有PostgreSQL.
- Cassandra -- deep storage
    - A "deep storage" LOB store/file system to hold the stored segments. 默认是local disk, 还有hdfs/S3/.                                

## Indexing Service
- Getting data into the Druid system requires an indexing process, as shown in the diagrams above.
- To load batch and real-time data into the system, as well as allow for alterations to the data stored in the system.

## Segments
- The output of the indexing process is called a "segment".
- Segments are the fundamental structure to store data in Druid.
- Segments contain the various dimensions and metrics in a data set, stored in a column orientation, as well as the indexes for those columns.

