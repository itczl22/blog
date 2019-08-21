在 Go http包的Server中, 每一个请求在都有一个对应的 goroutine 去处理. 请求处理函数通常会启动额外的 goroutine 用来访问后端服务, 比如数据库和RPC服务.  

用来处理一个请求的 goroutine 通常需要访问一些与请求特定的数据, 比如终端用户的身份认证信息、验证相关的token、请求的截止时间. 当一个请求被取消或超时时, 所有用来处理该请求的 goroutine 都应该迅速退出, 然后系统才能释放这些 goroutine 占用的资源.

在Google 内部开发了 Context 包, 专门用来简化对于处理单个请求的多个 goroutine 之间与请求域的数据、取消信号、截止时间等相关操作, 这些操作可能涉及多个 API 调用.

**正常控制goroutine的3种方法**

* waitGroup  
这是一种控制并发的方式, 这种尤其适用于好多个goroutine协同做一件事情的时候, 因为每个goroutine做的都是这件事情的一部分.只有全部的goroutine都完成这件事情才算是完成, 这是等待的方式.

* select + chan  
在控制goroutine里边对chan进行操作, 后台goroutine不停的监控这个chan看是否需要结束. 这种方式是比较优雅的结束一个goroutine的方式, 不过这种方式也有局限性, 如果有很多goroutine都需要控制结束怎么办呢？如果这些goroutine又衍生了其他更多的goroutine怎么办呢    

* Context  
比如一个网络请求Request, 每个Request都需要开启一个goroutine做一些事情, 这些goroutine又可能会开启其他的goroutine. 所以我们需要一种可以跟踪goroutine的方案, 才可以达到控制他们的目的. 这就是Go语言为我们提供的Context.  
context的基本原理如下[以WithCancel为例]：
  * p := context.Background() 返回一个空的Context, 这个空的Context一般用于整个Context树的根节点. It is never canceled, has no values, and has no deadline

  * ctx, cancel = context.WithCancel(p)函数, 创建一个可取消的子Context, 然后ctx当作参数传给goroutine使用, 这样就可以使用这个子Context跟踪这个goroutine

  * 在goroutine中, 使用select调用<-ctx.Done()判断是否要结束, 如果接收到值的话, 就表示可以结束goroutine了; 如果接收不到, 就会继续进行监控

  * 调用cancel它就可以发出取消指令, 然后我们3中的监控goroutine就会收到信号, 就会返回结束

**context常用函数说明**  

* context.Background  
p := context.Background() 返回一个空的Context, 这个空的Context一般用于整个Context树的根节点, 这是不可取消、没有值、没有最后期限. 他的返回值一般传给WithCancel、WithDeadline、WithTimeout做参数

* context.WithCancel  
ctx, cancel := context.WithCancel(p) 返回一个ctx和一个取消函数. 当cancel函数被调用或者 p.Done 被关闭时[哪个先发生算哪个], ctx.Done被关闭

* context.WithDeadline  
ctx, cancel = context.WithDeadline(p, dateline) 返回一个ctx和一个取消函数, 正常dateline到期后会自动执行cancel函数的  
但是提前主动调用, 可以尽快的释放, 避免等待过期时间之间的浪费;   
建议还是按照官方的说明使用, 养成良好的习惯, 在调用WithDeadline、WithTimeout之后**主动defer cancel()**  
The returned context's Done channel is closed when the deadline expires, when the returned cancel function is called   
or when the parent context's Done channel is closed, whichever happens first  

* context.WithTimeout  
ctx, cancel = context.WithTimeout(p, timeout), 其实调用的是WithDeadline方法, 等价于: WithDeadline(p, time.Now().Add(timeout))  

* context.WithValue  
ctx = context.WithValue(p, k, v), ctx会携带一个k-v键值对用来传递进程和API的请求范围数据, 而不是将可选参数传递给函数, 提供的key必须是可以比较的, 并且不能为string或者其他的内建的类型, 以避免不同包使用context导致的冲突  
使用WithValue需要定义自己的key类型. 如：type favContextKey string
ctx := context.WithValue(context.Background(), "trace_id", fvaContextKey("123"))
ctx.Value("trace_id").(string)

**Context**
```
 type Context interface {
   // Deadline 方法获取设置的dateline, 如果没有设置则ok == false
   Deadline() (deadline time.Time, ok bool)

   // Done 方法返回一个只读chan, 在goroutine中通过监听 Done 方法返回的chan, 如果该方法返回的chan可以读取, 则意味着parent context已经发起了取消请求, 此时应该做一些清理操作了, 然后退出goroutine, 释放资源. 之后 Err 方法会返回一个错误告知为什么 Context 被取消
   Done() <-chan struct{}

   // Err 方法返回context被取消的原因
   Err() error

   // Value 方法获取该Context上绑定的值, 这个值一般是并发安全的
   Value(key interface{}) interface{}
 }
```

**注意**  

* 不要把context存储在结构体中, 而是要显式地进行传递
* 把context作为第一个参数, 并且一般都把变量命名为ctx
* 就算是程序允许, 也不要传入一个nil的context, 如果不知道是否要用context的话, 用context.TODO()来替代
* context.WithValue()只用来传递请求范围的值, 不要用它来传递可选参数
* 就算是被多个不同的goroutine使用, context也是安全的
* When a Context is canceled, all Contexts derived from it are also canceled.
* Calling the CancelFunc cancels the child and its children, removes the parent's reference to the child, and stops any associated timers.
* Failing to call the CancelFunc leaks the child and its children until the parent is canceled or the timer fires.

**参考**  
* https://golang.org/pkg/context/
* https://blog.golang.org/context
