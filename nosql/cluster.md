__redis高可用集群的基础__
* 持久化(persistence) 是最简单的高可用方法, 它的主要作用是 数据备份, 即将数据存储在硬盘, 保证数据不会因进程退出而丢失

* 复制(replication) 是高可用redis的基础, 哨兵和集群都是在 复制基础上实现高可用的, 复制主要实现了数据的多机备份以及对于读操作的负载均衡和简单的故障恢复, 缺陷是故障恢复无法自动化、写操作无法负载均衡、存储能力受到单机的限制

* 分片(partition) 是将数据拆分到多个redis实例, 这样每个redis实例将只包含完整数据的一部分, 这样可以突破单机内存的限制

* 哨兵(sentinel) 在复制的基础上, 哨兵实现了自动化的故障转移(overload)

* 集群(cluster) 通过集群, redis解决了写操作无法负载均衡, 以及 存储能力受到单机限制的问题, 实现了较为完善的高可用方案

__目前实现redis高可用集群的方式主要有3种__
* twemproxy  
https://github.com/twitter/twemproxy

* codis  
https://github.com/CodisLabs/codis

* redis cluster  
https://redis.io/topics/cluster-tutorial

__3种实现方式的对比__ 

|     | Redis Cluster | Twemproxy | Codis |
| :---- | :------------ | :------------ | :------ |
| 特点 | 去中心化, 节点对等 | c语言, 单线程 <br> 一致性hash分片/取模分片 | Go语言, 支持协程 |
| 优点 | 直连性能高 <br> 自动故障检测和failover, 无需重新搭建sentinel支持 <br> 水平扩展, 数据在节点间均衡分配, 重新分片不需要重启 <br> Slot 迁移中数据可用 | 开发简单, 对应用几乎透明 | 屏蔽分片细节, 基于CRC32%1024分片 <br> 平滑扩容, 仅影响正在迁移的key的写操作, 非阻塞按分片迁移 <br> 支持读写分离, pipeline <br> 运维能力好: 拥有管理后台, 管理proxy、redis-server、sentinel, 能基于界面准备切换及rewrite config, slot分配, 迁移 <br> 有只管的容量水位展示及命令请求量, 失败量及qps曲线图|
| 缺点 | 客户端需要升级smart client, 理解集群版协议 <br>  读写分离支持比较复杂, 需要客户端定制改造 <br> 客户端维护路由表(slot->node)的开销 <br> 不支持pipeline <br> 同步阻塞按key迁移, 迁移速度慢 <br> 运维成本高, 基于ruby定制开发 <br> 架构比较新, 最佳实践较少 | 无法平滑扩容, 静态分片需要重启 <br> 已经被tweeter放弃, 有6年没有更新了 <br> 代理影响性能 | 代理影响性能 <br> 组件过多, 需要很多机器资源 |


__参考__

* https://chuansongme.com/n/1870094951614
