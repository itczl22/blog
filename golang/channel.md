Don’t communicate by sharing memory; share memory by communicating.

#### channel的基本操作

```
  ch := make(chan int)   // a create statement; ch has type 'chan int', also is a unbuffered channel  

  ch = make(chan int, 3) // buffered channel with capacity 3

  ch <- x       // a send statement

  <-ch          // a receive statement; result is discarded

  x = <-ch      // a receive expression in an assignment statement

  x, ok :<-ch   // 通过ok判断管道是否关闭

  close(ch)     // a close statement; Close, which sets a flag indicating that no more values will ever be sent on this channel; subsequent attempts to send will panic.

  cap(ch)       // capacity of buffer

  len(ch)       // the number of elements currently buffered

  for c := range ch {...}  // 管道的循环接收, 针对channel的range, 只有channel被关闭, range才会结束.
```


#### channel的注意事项

* with maps, a channel is a reference to the data structure created by make. 引用类型

* channels of the same type may be compared using ==. The comparison is true if both are `references to the same channel` data structure[必须引用同一个chan, 而不是同类型的chan]. A channel may also be compared to nil.

* 通过chan发送的消息有两个重要作用: 一个是发送消息，一个是通知事件的发生. 当我们希望强调后者时，我们称之为消息事件. 当事件没有附加信息时, 它的唯一目的是同步，我们将通过使用一个元素类型为struct {}的通道来传递 ch <- struct{}{}

* This is a more convenient syntax for receiving all the values sent on a channel and terminating the loop after the last one. `for x, ok := range naturals {}`

* Also, when we know an upper bound on the number of values that will be sent on a channel, it’s not unusual to create a buffered channel of that size and perform all the sends before the first value is received. Failure to allocate sufficient buffer capacity would cause the program to deadlock.


* 对于一个已初始化, 但并未关闭的通道来说, 收发操作一定不会引发 panic. 但是通道一旦关闭, 再对它进行发送操作, 就会引发 panic. 另外, 如果我们试图关闭一个已经关闭了的通道, 也会引发 panic.

* Attempting to close an already-closed channel causes a panic, as does closing a nil channel.

* 当一个被关闭的channel中已经发送的数据都被成功接收后，后续的接收操作将不再阻塞，它们会立即返回一个零值.

* 接收操作是可以感知到通道的关闭的, 并能够安全退出. 因此, 通过接收表达式的第二个结果值, 来判断通道是否关闭是可能有延时的. 所以除非有特殊的保障措施, 否则千万不要让接收方关闭通道, 而应当让发送方做这件事.

* 元素值在经过通道传递时会被复制, 那么这个复制是浅拷贝. 不过, 要是传指针的话要自己保证安全, 比如原始数据放篡改之类的.

* Pipelines may be found in long-running server programs where channels are used for lifelong communication between goroutines containing infinite loops. Channels can be used to connect goroutines together so that the output of one is the input to another. `This is called a pipeline`.

* waitGroup is a common and idiomatic pattern for looping in parallel when we don’t know the number of iterations. 同步控制有时候用chan, 有时候waitGroup, 咋么选择：并发循环时, 当不知道迭代的次数时一般使用waitGroup.


#### 单向管道

* The type chan<- int, a send-only channel of int, allows sends but not receives. Conversely, the type <-chan int, a receive-only channel of int, allows receives but not sends. Violations of this discipline are detected at compile time. 单向通道存在的意义就是“约束”代码.

* Conversions from bidirectional to unidirectional channel types are permitted in any assignment. There is no going back, however: once you have a value of a unidirectional type such as chan<- int, there is no way to obtain from it a value of type chan int that refers to the same channel data structure. 不能单向转双向，只能双向转单向.

* 在实际传参的时候只需要把一个元素类型匹配的双向通道传给它就行了，没必要用单向通道，因为 Go 语言在这种情况下会自动地把双向通道转换为函数所需的单向通道.

* 单项通道一般用到接口中方法的定义上, 用来约束接口的实现, 一个是参数、一个是返回值


#### 缓存通道
* On the other hand, if an earlier stage is consistently faster than the following stage , the buffer between them will spend most of its time full. Conversely, if the later stage is faster, the buffer will usually be empty. A buffer provides no benefit in this case.


#### 非缓存通道

* 对于非缓冲通道, 无论是发送操作还是接收操作, 一开始执行就会被阻塞, 直到配对的操作也开始执行时两个管道才会继续传递. 由此可见, 非缓冲通道是在用同步的方式传递数据. 并且, 数据是直接从发送方复制到接收方的, 中间并不会用非缓冲通道做中转. 相比之下, 缓冲通道则在用异步的方式传递数据. 在大多数情况下, 缓冲通道会作为收发双方的中间件. 元素值会先从发送方复制到缓冲通道, 之后再由缓冲通道复制给接收方. 但是, 当发送操作在执行的时候发现空的通道中, 正好有等待的接收操作, 那么它会直接把元素值复制给接收方. 对于值为nil的通道, 不论它的具体类型是什么, 对它的发送操作和接收操作都会永久地处于阻塞状态.

* A send operation on an unbuffered channel blocks the sending goroutine until another goroutine executes a corresponding receive on the same channel, at which point the value is transmitted and both goroutines may continue. Because of this, unbuffered channels are sometimes called synchronous channels


#### channel的回收

* You needn’t close every channel when you’ve finished with it. It’s only necessary to close a channel when it is important to tell the receiving goroutines that all data have been sent. A channel that the garbage collector determines to be unreachable will have its resources reclaimed whether or not it is closed. gc会自动回收channel的

* Unlike garbage variables, leaked goroutines are not automatically collected, so it is important to make sure that goroutines terminate themselves when no longer needed. 携程泄漏是没法被gc回收的

* Go的GC只会清理被分配到堆上的、不再有任何引用的对象. 所以通道必须自己手动关闭.


### select

select语句是专门为通道而设计的，它可以包含若干个候选分支，每个分支中的case表达式都会包含针对某个通道的发送或接收操作.

```
select {
case <-ch1:
  // ...
case x, ok := <-ch2:
  // ...use x...
case ch3 <- y:
  // ...
default:
  // ...
}
```

如果有默认分支, 那么无论涉及通道操作的表达式是否有阻塞, select语句都不会被阻塞. 如果没有加入默认分支, 那么一旦所有的case表达式都没有满足求值条件, 那么select语句就会被阻塞, 直到至少有一个case表达式满足条件为止.  

select语句只能对其中的每一个case表达式各求值一次, 如果我们想连续或定时地操作其中的通道的话, 就往往需要通过在for语句中嵌入select语句的方式实现. 但要注意, 简单地在select语句的分支中使用break语句, 只能结束当前的select语句的执行, 而并不会对外层的for语句产生作用  

Each case specifies a communication (a send or receive operation on some channel) and an associated block of statements.必须包含一个接收或者发送表达式  

select语句包含的候选分支中的case表达式都会在该语句执行开始时`先被求值`, 并且求值的顺序是依从代码编写的顺序从上到下从左到右的. 对于每一个case表达式, 如果其中的发送表达式或者接收表达式在被求值时, `相应的操作正处于阻塞状态, 那么对该case表达式的求值就是不成功的`.  

仅当select语句中的`所有case表达式都被求值完毕后才会开始选择候选分支`. 这时候, 它只会挑选满足选择条件的候选分支执行. 如果所有的候选分支都不满足选择条件, 那么默认分支就会被执行. 如果这时没有默认分支, 那么select语句就会立即进入阻塞状态, 直到至少有一个候选分支满足选择条件为止. 一旦有一个候选分支满足选择条件, select语句(或者说它所在的 goroutine)就会被唤醒, 这个候选分支就会被执行.  

如果select语句发现同时有多个候选分支满足选择条件, 那么它就会用一种伪随机的算法在这些分支中选择一个并执行. 一条select语句中只能够有一个默认分支, 并且, 默认分支只在无候选分支可选时才会被执行, 这与它的编写位置无关.  

select语句的每次执行, 包括case表达式求值和分支选择, 都是独立的. 至于它的执行是否是并发安全的, 就要看其中的case表达式以及分支中是否包含并发不安全的代码了.  
