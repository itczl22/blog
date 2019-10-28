#### 什么是CSP

* 官方解释: http://www.usingcsp.com/cspbook.pdf  

* 全称: Communicating Sequential Processes  

* CSP并发模型是在1970年左右提出的概念, 不同于传统的多线程通过共享内存来通信, CSP讲究的是“以通信的方式来共享内存”. 用于描述多个独立的并发实体通过共享的通讯channel进行通信的并发模型. CSP中channel是一级对象, 它不关注发送消息的实体, 而关注与发送消息时使用的channel. 严格来说, CSP 是一个理论研究, 用于描述并发系统中的互动模式, 也因此成为一众面向并发的编程语言的理论源头, 并衍生出了 Occam/Limbo/Golang  

* 而具体到编程语言, 如 golang, 其实只用到了 CSP 的很小一部分, 即理论中的 process/channel, 对应到语言中的 goroutine/channel. go中channel是被单独创建并且可以在进程之间传递, 它的通信模式类似于 master-worker 模式的, 一个实体通过将消息发送到channel 中, 然后由监听这个 channel 的实体处理, 两个实体之间是匿名的. 这个就实现实体中间的解耦, 其中 channel是同步的, 一个消息被发送到 channel 中, 最终是一定要被另外的实体消费掉的, 在实现原理上其实类似一个阻塞的消息队列.

* Do not communicate by sharing memory; instead, share memory by communicating.

#### Why build concurrency on the ideas of CSP  

* Concurrency and multi-threaded programming have over time developed a reputation for difficulty. We believe this is due partly to complex designs such as pthreads and partly to overemphasis on low-level details such as mutexes, condition variables, and memory barriers. Higher-level interfaces enable much simpler code, even if there are still mutexes and such under the covers.  

* One of the most successful models for providing high-level linguistic support for concurrency comes from Hoare's Communicating Sequential Processes, or CSP. Occam and Erlang are two well known languages that stem from CSP. Go's concurrency primitives derive from a different part of the family tree whose main contribution is the powerful notion of channels as first class objects. Experience with several earlier languages has shown that the CSP model fits well into a procedural language framework.


#### CSP在golang中的实现  

* go的CSP并发模型是通过goroutine和channel来实现的  

* goroutine 是go语言中并发的执行单位. 他是一个携程

* channel是go语言中各个并发结构体(goroutine)之前的通信机制. 通俗的讲就是各个goroutine之间通信的"管道", 有点类似于Linux中的管道

* 理论上, channel 和 goroutine 之间没有从属关系. goroutine 可以订阅任意个 channel, channel 也并不关心是哪个 goutine 在利用它进行通信. goutine 围绕 channel 进行读写, 形成一套同步阻塞的并发模型  

* 实现演示: https://godoc.org/github.com/thomas11/csp
