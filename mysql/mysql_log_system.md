Mysql 的日志主要分为6种

* 错误日志(Error Log)
Mysqld 启动、运行、停止所遇到的问题

* 查询日志(General Query Log)
客户端的连接及从客户端接收到的查询记录

* 慢查询日志(Slow Query Log)
执行时间超过 long_query_time 的查询记录

* 二进制日志(Binary Log)
就是常说的binlog，记录所有修改数据的记录，同时也用于主从复制、数据恢复

* 中继日志(Relay Log)
主从复制的时候，从master同步过来的数据更改记录

* 事务日志(Transaction Log)
主要和mysql事务相关的，包括 redo log 和 undo log

其中事务日志的redo log 和 undo log 属于存储引擎层的日志，中继日志是一个中间层，其他的都是server层的日志

__Redo Log__


__Undo Log__

__Binary Log__

