主从复制(repliaction)是指将一台redis服务器的数据复制到其他redis服务器. 前者称为主节点(master), 后者称为从节点(slave). 数据的复制是单向的, 只能由主节点到从节点  
默认情况下, 每台redis服务器都是主节点, 且一个主节点可以有多个从节点(或没有从节点), 但一个从节点只能有一个主节点  


__主从复制的作用__
* 数据冗余: 主从复制实现了数据的热备份, 是持久化之外的另一种数据冗余方式 

* 故障恢复: 当主节点出现问题时可以由从节点提供服务,实现快速的故障恢复

* 负载均衡: 在主从复制的基础上配合读写分离, 可以由主节点提供写服务, 由从节点提供读服务, 分担服务器负载

* 高可用基石: 主从复制是哨兵和集群能够实施的基础


__主从复制的特点__

* redis使用异步复制, slave异步的进行批量确认

* 一个master可以有多个slave

* slave可以有多个自己的子slave, 4.0开始所有的slave都从master去复制数据

* redis复制在master端无阻塞的, 这意味着当一个或多个slave执行初始同步或部分重新同步时, master将继续处理查询

* 复制在slave端几乎也是无阻塞的, 在同步的时候依然可以提供旧数据的查询服务, 当然也可以配置不提供服务, 通过replica-serve-stale-data来配置. 

* 在初始同步之后必须删除旧的数据集然后再加载新的数据集. 从Redis 4.0开始可以对Redis进行配置, 以使旧数据集的删除发生在其他线程中, 但是加载新的初始数据集仍将在主线程中发生


__复制的3种方式__

* 命令流同步
  * 当master和slave连接良好时, master通过向slave发送命令流来使slave保持更新, 以便复制由于以下原因而对主节点上发生的数据集产生的影响: 客户端写入、密钥已过期及任何其他更改master数据集的操作

* 部分同步
  * 2.8开始, 当master和slave之间断开重连之后, 他们之间可以采用部分复制方式代替全量同步

  * 由于网络问题或由于主从探活超时, 当master和slave之间的连接断开时, slave将重新连接并尝试进行部分重新同步, 这意味着它将只同步连接断开期间错过的命令流

  * 每个master都有一个复制ID, 它是一个较大的伪随机字符串, 用于标记给定的数据集. 每个master还采用一个偏移量, 该偏移量会随着发送到slave的字节的增量而增加. 即使实际上未连接任何副本, 复制偏移也会增加. 因此使用给定的一对 `Replication ID, offset` 来标识一段特定的master数据集

  * slave使用`PSYNC`(旧版的SYNC不支持部分重新同步)命令来发送其旧的master的replication id和到目前为止已处理的偏移量offset, 这样master可以通过命令的形式将连接断开这段时间的增量数据发送给slave执行, 之后复制工作可以继续执行了

  * 但是如果master没有足够的`backlog`(如网络中断时间过长导致master没有能够完整地保存中断期间执行的写命令)或者slave发送的replication id和当前master的id不相等, 则会发生完全重新同步

* 完全同步
  * 如果无法进行部分重新同步, 则slave将要求完全重新同步, 在该过程中master需要创建其所有数据的快照(rdb文件)将其发送到slave, 然后在master数据集发生更改时继续发送命令流

  * master启用一个子进程保存RDB文件, 同时它开始缓冲从客户端收到的所有新的写命令, rdb保存完成后master将rdb文件传输到slave, slave首先清除自己的旧数据然后载入接收的RDB文件将其加载到内存中

  * 然后master将所有缓冲区的数据以命令的形式发送到slave, 这是作为命令流完成的并且与Redis协议本身的格式相同

  * master/slave此后会不断通过异步方式进行命令的同步, 达到最终数据的同步一致


__无盘复制 diskless__

* 通常完全重新同步需要在磁盘上创建数据快照RDB文件, 然后从磁盘重新加载相同的RDB, 以便为slave提供数据. 即disk-backed

* 对于慢速磁盘, 这对master而言可能是非常压力的操作, Redis 2.8.18支持无盘复制. 无需将磁盘用作中间存储, 子进程直接将快照通过socket发送给副本

* 但是diskless复制有清空数据的可能性, 需要根据具体的场景选择使用

* diskless 必须谨慎处理此设置, 因为重新启动的master将以空数据集开始, 如果副本尝试与其同步则副本也将被清空


__主从复制存在的问题__

* 一旦主节点宕机, 从节点晋升成主节点, 同时需要修改应用方的主节点地址, 还需要命令所有从节点去复制新的主节点, 整个过程需要 人工干预

* 主节点的写能力受到单机的限制

* 主节点的存储能力受到单机的限制

* 如果复制中断后, 从节点会发起psync, 此时如果同步不成功则会进行全量同步, 主库执行全量备份的同时可能会造成毫秒或秒级的卡顿

* 由于人工介入, 操作期间会影响服务的正常使用, 哨兵sentinel可以解决这个问题


__复制的相关配置__

* replicaof <masterip> <masterport> 
  需要同步的master的ip+port

* masterauth <master-password>      
  master的密码, 如果master设置了密码保护(通过"requirepass"选项来配置)

* replica-serve-stale-data yes      
  当一个slave失去和master的连接或者同步正在进行中, slave的行为可以有两种:  
  yes: slave会继续响应客户端请求, 但是可能获取的是正常数据, 或者是过时了的数据, 也可能是空数据  
  no: slave会回复一个错误 "SYNC with master in progress" 来处理各种请求  
  除了 INFO, replicaOF, AUTH, PING, SHUTDOWN, REPLCONF, ROLE, CONFIG, SUBSCRIBE,
  UNSUBSCRIBE, PSUBSCRIBE, PUNSUBSCRIBE, PUBLISH, PUBSUB, COMMAND, POST, HOST and LATENCY  
  
* replica-read-only yes     
  配置salve实例是否接受写操作

* repl-diskless-sync no
  是否启用无盘复制
  
* repl-diskless-sync-delay 5
  控制第一个slave请求同步之后多久开始传输数据, 目的是为了等待更多slave到达, diskless启用的情况下生效
  
* repl-ping-replica-period 60
  slave以指定的时间间隔向master发送ping命令进行探活
  
* repl-timeout 300
  同步的超时时间, 确保这个值大于repl-ping-slave-period, 否则在主从流量不高时每次都会检测到超时

* repl-disable-tcp-nodelay no
  是否在slave套接字发送SYNC之后禁用 TCP_NODELAY, 该选项主要用来控制是否合并发送tcp包, 目前默认都是关闭的, 主要是早年带宽不足的情况下使用的   
  yes: redis将使用更少的TCP包和带宽来向slaves发送数据, 但是这将使数据传输到slave上有延迟，Linux内核的默认配置会达到40毫秒  
  no: 数据传输到salve的延迟将会减少, 但高流量情况或主从之间的跳数过多时把这个选项设置为yes是个不错的选择
  
* repl-backlog-size 20mb
  设置数据备份的backlog大小, backlog是一个slave在一段时间内断开连接时记录salve数据的缓冲  
  所以一个slave在重新连接时不必要全量的同步, 而是一个增量同步就足够了, 将在断开连接的这段时间内slave丢失的部分数据传送给它  
  backlog越大, slave能够进行增量同步并且允许断开连接的时间就越长, backlog只分配一次并且至少需要一个slave连接

* repl-backlog-ttl 300
  当master在指定的时间内不再与任何slave连接, backlog将会释放

* slave-priority 100
  slave的优先级, 如果master不再正常工作了, 哨兵将用它来选择一个slave提升为master. 优先级数字小的salve会优先考虑提升为master
  0作为一个特殊的优先级标识这个slave不能作为master


__参考__

* https://redis.io/topics/replication
