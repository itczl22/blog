Don’t communicate by sharing memory; share memory by communicating.

```
  ch := make(chan int)   // a create statement; ch has type 'chan int', also is a unbuffered channel  

  ch = make(chan int, 3) // buffered channel with capacity 3

  ch <- x   // a send statement

  x = <-ch  // a receive expression in an assignment statement

  <-ch      // a receive statement; result is discarded

  close(ch) // a close statement; Close, which sets a flag indicating that no more values will ever be sent on this channel; subsequent attempts to send will panic.

  cap(ch)   // capacity of buffer

  len(ch)   // the number of elements cur- rently buffered
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

* Conversions from bidirectional to unidirectional channel types are permitted in any assignment. There is no going back, however: once you have a value of a unidirectional type such as chan<- int, there is no way to obtain from it a value of type chan int that refers to the same channel data structure. 只能单向转双向，不能双向转单向.

* Channels are deeply connected to goroutine scheduling, and without another goroutine receiving from the channel, a sender—and perhaps the whole program—risks becoming blocked forever.

* 携程泄漏是指某个携程永久的被阻塞而无法释放

* Unlike garbage variables, leaked goroutines are not automatically collected, so it is important to make sure that goroutines terminate them- selves when no longer needed.

* Also, when we know an upper bound on the number of values that will be sent on a channel, it’s not unusual to create a buffered channel of that size and perform all the sends before the first value is received.
