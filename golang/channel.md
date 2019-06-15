Don’t communicate by sharing memory; share memory by communicating.

#### channel的基本操作
```
  ch := make(chan int)   // a create statement; ch has type 'chan int', also is a unbuffered channel  

  ch = make(chan int, 3) // buffered channel with capacity 3

  ch <- x   // a send statement

  x = <-ch  // a receive expression in an assignment statement

  <-ch      // a receive statement; result is discarded

  close(ch) // a close statement; Close, which sets a flag indicating that no more values will ever be sent on this channel; subsequent attempts to send will panic.

  cap(ch)   // capacity of buffer

  len(ch)   // the number of elements cur- rently buffered
  
  for c := range ch {...}  // 管道的循环接收
```


* Receive operations on a closed channel yield the values that have been sent until no more values are left; any receive operations thereafter complete immediately and yield the zero value of the channel’s element type.

* As with maps, a channel is a reference to the data structure created by make

* Two channels of the same type may be compared using ==. The comparison is true if both are references to the same channel data structure. A channel may also be compared to nil.

* Two channels of the same type may be compared using ==. The comparison is true if both are `references to the same channel` data structure[必须引用同一个chan, 而不是同类型的chan]. A channel may also be compared to nil.

* A send operation on an unbuffered channel blocks the sending goroutine until another goroutine executes a corresponding receive on the same channel, at which point the value is transmitted and both goroutines may continue. Conversely, if the receive operation was attempted first, the receiving goroutine is blocked until another goroutine performs a send on the same channel. Because of this, unbuffered channels are sometimes called synchronous channels

* 通过chan发送的消息有两个重要作用: 一个是发送消息，一个是通知事件的发生. 当我们希望强调后者时，我们称之为消息事件. 当事件没有附加信息时，它的唯一目的是同步，我们将通过使用一个元素类型为struct {}的通道来传递 ch <- struct{}{}

* Pipelines like this may be found in long-running server programs where channels are used for lifelong communication between goroutines containing infinite loops. Channels can be used to connect goroutines together so that the output of one is the input to another. This is called a pipeline.

* This is a more convenient syntax for receiving all the values sent on a channel and terminating the loop after the last one. `for x := range naturals {}`

* You needn’t close every channel when you’ve finished with it. It’s only necessary to close a channel when it is important to tell the receiving goroutines that all data have been sent. A channel that the garbage collector determines to be unreachable will have its resources reclaimed whether or not it is closed. (Don’t confuse this with the close operation for open files. It is important to call the Close method on every file when you’ve finished with it.)

* Attempting to close an already-closed channel causes a panic, as does closing a nil channel.

* The type chan<- int, a send-only channel of int, allows sends but not receives. Conversely, the type <-chan int, a receive-only channel of int, allows receives but not sends. Violations of this discipline are detected at compile time.

* Conversions from bidirectional to unidirectional channel types are permitted in any assignment. There is no going back, however: once you have a value of a unidirectional type such as chan<- int, there is no way to obtain from it a value of type chan int that refers to the same channel data structure. 不能单向转双向，只能双向转单向.

* Channels are deeply connected to goroutine scheduling, and without another goroutine receiving from the channel, a sender—and perhaps the whole program—risks becoming blocked forever.

* 携程泄漏是指某个携程永久的被阻塞而无法释放

* Unlike garbage variables, leaked goroutines are not automatically collected, so it is important to make sure that goroutines terminate them- selves when no longer needed.

* Also, when we know an upper bound on the number of values that will be sent on a channel, it’s not unusual to create a buffered channel of that size and perform all the sends before the first value is received. Failure to allocate sufficient buffer capacity would cause the program to deadlock.

* On the other hand, if an earlier stage is consistent ly faster than the following stage , the buffer between them will spend most of its time full. Conversely, if the later stage is faster, the buffer will usually be empty. A buffer provides no benefit in this case.

* waitGroup is a common and idiomatic pattern for looping in parallel when we don’t know the number of iterations. 同步控制有时候用chan, 有时候waitGroup, 咋么选择：并发循环时, 当不知道迭代的次数时一般使用waitGroup.

* 针对channel的range, 只有channel被关闭, range才会结束.

* 对于非缓冲通道, 无论是发送操作还是接收操作, 一开始执行就会被阻塞, 直到配对的操作也开始执行才会继续传递. 由此可见, 非缓冲通道是在用同步的方式传递数据. 并且, 数据是直接从发送方复制到接收方的, 中间并不会用非缓冲通道做中转. 相比之下, 缓冲通道则在用异步的方式传递数据. 在大多数情况下, 缓冲通道会作为收发双方的中间件. 元素值会先从发送方复制到缓冲通道, 之后再由缓冲通道复制给接收方. 但是, 当发送操作在执行的时候发现空的通道中, 正好有等待的接收操作, 那么它会直接把元素值复制给接收方. 对于值为nil的通道, 不论它的具体类型是什么, 对它的发送操作和接收操作都会永久地处于阻塞状态.

* 对于一个已初始化, 但并未关闭的通道来说, 收发操作一定不会引发 panic. 但是通道一旦关闭, 再对它进行发送操作, 就会引发 panic. 另外, 如果我们试图关闭一个已经关闭了的通道, 也会引发 panic. 

* 接收操作是可以感知到通道的关闭的, 并能够安全退出. 因此, 通过接收表达式的第二个结果值, 来判断通道是否关闭是可能有延时的. 所以除非有特殊的保障措施, 否则千万不要让接收方关闭通道, 而应当让发送方做这件事.

* 元素值在经过通道传递时会被复制, 那么这个复制是浅拷贝. 不过, 要是传指针的话要自己保证安全, 比如原始数据放篡改之类的.

* Go的GC只会清理被分配到堆上的、不再有任何引用的对象. 所以通道必须自己手动关闭.

* 单项通道一般用到接口中方法的定义上, 用来约束接口的实现, 一个是参数、一个是返回值

* 我们在调用SendInt函数的时候，只需要把一个元素类型匹配的双向通道传给它就行了，没必要用发送通道，因为 Go 语言在这种情况下会自动地把双向通道转换为函数所需的单向通道。

#### select
* select语句只能与通道联用, 所以每个case表达式中都只能包含操作通道的表达式，比如接收表达式.

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

如果像上述示例那样加入了默认分支，那么无论涉及通道操作的表达式是否有阻塞，select语句都不会被阻塞。如果那几个表达式都阻塞了，或者说都没有满足求值的条件，那么默认分支就会被选中并执行。 如果没有加入默认分支，那么一旦所有的case表达式都没有满足求值条件，那么select语句就会被阻塞。直到至少有一个case表达式满足条件为止。 还记得吗？我们可能会因为通道关闭了，而直接从通道接收到一个其元素类型的零值。所以，在很多时候，我们需要通过接收表达式的第二个结果值来判断通道是否已经关闭。一旦发现某个通道关闭了，我们就应该及时地屏蔽掉对应的分支或者采取其他措施。这对于程序逻辑和程序性能都是有好处的。 select语句只能对其中的每一个case表达式各求值一次。所以，如果我们想连续或定时地操作其中的通道的话，就往往需要通过在for语句中嵌入select语句的方式实现。但这时要注意，简单地在select语句的分支中使用break语句，只能结束当前的select语句的执行，而并不会对外层的for语句产生作用。这种错误的用法可能会让这个for语句无休止地运行下去。
