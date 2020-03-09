Mysql 的日志主要分为7种

* 错误日志(Error Log)
Mysqld 启动、运行、停止所遇到的问题

* 查询日志(General Query Log)
客户端的连接及从客户端接收到的查询记录

* 慢查询日志(Slow Query Log)
执行时间超过 long_query_time 的查询记录

* 二进制日志(Binary Log)
所有修改数据的记录，同时也用于主从复制

* 事务日志(Transaction Log)

* 中继日志(Relay Log)

* DDL 日志

