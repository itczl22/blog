# Linux IO

### 基本概念

**阻塞/非阻塞**
针对的对象是调用者自己本身的情况
* 阻塞
指调用者在调用某一个函数后，一直在等待该函数的返回值，线程处于挂起状态
* 非阻塞
指调用者在调用某一个函数后，不等待该函数的返回值，线程继续运行其他程序（执行其他操作或者一直遍历该函数是否返回了值）

**同步/异步**
针对的对象是被调用者的情况
* 同步
指的是被调用者在被调用后，操作完函数所包含的所有动作后，再返回返回值
* 异步
指的是被调用者在被调用后，先返回返回值，然后再进行函数所包含的其他动作

**缓存 I/O**
- 缓存 I/O 又被称作标准 I/O，大多数文件系统的默认 I/O 操作都是缓存 I/O
- 在 Linux 的缓存 I/O 机制中，操作系统会将 I/O 的数据缓存在文件系统的页缓存(page cache)中，即数据会先被拷贝到操作系统内核的缓冲区中，然后才会从操作系统内核的缓冲区拷贝到应用程序的地址空间
- 数据在传输过程中需要在应用程序地址空间和内核进行多次数据拷贝操作，这些数据拷贝操作所带来的 CPU 以及内存开销是非常大的

### IO模式

对于一次IO访问（以read举例）当一个read操作发生时，它会经历**两个阶段**
- 等待数据准备 (Waiting for the data to be ready)
- 将数据从内核拷贝到进程中 (Copying the data from the kernel to the process)

针对这两个阶段，linux系统产生了下面五种网络模式的方案
- 阻塞 I/O（blocking IO）
- 非阻塞 I/O（non-blocking IO）
- I/O 多路复用（ IO multiplexing）
- 信号驱动 I/O（ signal driven IO）
- 异步 I/O（asynchronous IO）

**阻塞IO：blocking io**
- blocking IO的特点就是在IO执行的两个阶段都被block了
- 这可不行，一个进程什么都干不了了

**非阻塞IO：non-blocking io**
- nonblocking IO 的特点是用户进程需要不断的主动询问kernel数据好了没有
- 第一阶段是非阻塞，但是第二阶段实际上还是阻塞的
- 这也不行，太浪费cpu了，有事没事就去询问
  
**I/O 多路复用：IO multiplexing**
- IO multiplexing 比如我们说的select、poll、epoll，他的好处就在于单个process就可以同时处理多个网络连接的IO。首先他不会阻塞所有的进程只会阻塞当前进程；其次他不会不停地询问kernel，而是有数据的时候主动去处理；刚好避免了 blocking io 和 non-blocking io 的弊端
- 基本原理就是select，poll，epoll这个function会不断的轮询所负责的所有socket，当某个socket有数据到达了，就通知用户进程
- I/O 多路复用的特点是通过一种机制一个进程能同时等待多个文件描述 符，而这些文件描述符（套接字描述符）其中的任意一个进入读就绪状态，相应函数（select、poll、epoll_wait）就可以返回
- 相对于blocking io而言，他阻塞两次，select|poll|epoll_wait和recv. 所以对于单个连接还不如 blocking io，但是 IO multiplexing 的优势在于它可以同时处理多个connection
- 如果处理的连接数不是很高的话，使用 IO multiplexing 的web server 不一定比使用multi-threading + blocking IO 的 web server 性能更好，可能延迟还更大
- select/epoll的优势并不是对于单个连接能处理得更快，而是在于能处理更多的连接

- io多路复用一般对每个socket都设置为non-blocking, 因为正常是有数据才会read, 所以没必要阻塞, 万一没读到数据也不会阻塞整个进程

**信号驱动 I/O: signal driven IO**
- 也是一种io multiplexing，典型代表就是poll，因为select有FD_SETSIZE的限制，所有poll比select用的多，但是目前都用epoll

**异步IO：asynchronous io**
- 用户进程发起read操作之后，立刻就可以开始去做其它的事，kernel会等待数据准备完成，然后将数据拷贝到用户内存，当这一切都完成之后，kernel会给用户进程发送一个signal，告诉它read操作完成了(aio，但是在Linux中用的很少)

**IO总结**
- 阻塞IO、非阻塞IO、I/O多路复用都属于**同步IO**，因为他们的第二阶段都需要阻塞
- 而异步IO两个阶段都彻底不需要阻塞，直到数据拷贝到用户程序后发一个信号通知一下就可以了
- 与多进程和多线程技术相比，I/O多路复用技术的最大优势是系统开销小，系统不必创建进程/线程，也不必维护这些进程/线程，从而大大减小了系统的开销

### IO多路复用的实现

**select**

- select函数
```c
    int select (
      int fds,                // 建议是监控的文件描述符号的最大值+1，注意不是监控的文件描述个数的最大值而是描述符号的最大值
                              // 因为他不知道那个描述符就绪，所以是通过循环扫描来获取的
      fd_set* readfds;        // 读文件描述符号集合，输入的是要监控的文件描述符号，输出的有就绪(数据)的文件描述符号集合
      fd_set* writefds,       // 写文件描述符集合，输入的是要监控的文件描述符号，输出的是就绪(没有数据)的文件描述符号集合
      fd_set* errfds,         // 错误文件描述符号集合，在网络中 writefds 和 errfds 描述符集合我们一般不管
      struct timeval* timeout // 指定select函数阻塞时间，NULL表示永久阻塞，timeval两个字段都为0则立刻返回. 输出剩余的等待时间
    );
```

- 注意
  - 这3个集合如果不需要哪个可以直接赋值为NULL或0
  - fd_set是一个固定大小的buffer，FD_SET和FD_CLR的值必须是 0-FD_SETSIZE，否则发生未定义错误
- 返回
  - 大于 0 ：3个集合发生改变的文件描述符号总个数
  - 等于 0 : 时间限制过期expire
  - 等于 -1：异常

- 特点
  - select 函数监视的文件描述符分3类，分别是writefds、readfds、和exceptfds，就是3个位图
  - 调用后 select 函数会阻塞，直到有描述符就绪(有数据 可读、可写、或者有except)或者超时(timeout指定等待时间，如果立即返回设为null即可)函数返回
  - 当select函数返回后，可以 通过遍历fdset，来找到就绪的描述符
- 弊端
  - 有最大文件描述符的限制，Linux为1024.
  - 每次都需要无差别轮询文件描述符集合fdset，随着连接的增加，性能也在直线下降. O(n)级，n为连接数
- 程序示例
  - [select demo](/linux/socket/select.c "select demo")

**poll**

- poll函数
```c
      int poll(
        struct pollfd *fds,      // 被监听的文件描述符集合，一个结构体数组
        nfds_t nfds,             // 被监听的文件描述符个数，即上边fds数组所包含的结构体的个数
        int timeout              // poll函数最大block时间，单位ms，负数表示无限等待，0表示不等待，整数表示等待时间
      );
      struct pollfd {
        int   fd;               // file descriptor，被监听的文件描述符，负数表示不监听
        short events;           // requested events，输入参数，需要被监听的事件，0表示忽略所有事件
        short revents;          // returned events，输出参数，发生的事件
      };
```

- 返回值：
  - 大于 0：表示revents大于0的fd的个数
  - 等于 0：超时，或者没有revents返回
  - 等于-1：出错
     
- 特点
  - 不同于 select 使用三个位图来表示三个 fdset 的方式，poll 使用一个 pollfd 的指针实现，即 pollfd 数组，一个连接对应pollfd中的一个元素
  - pollfd 结构包含了要监视的 event 和发生的 event，不再使用 select “参数-值”传递的方式
  - select函数一样，poll返回后，需要轮询pollfd来获取就绪的描述符
  - pollfd并没有最大数量限制（但是数量过大后性能也是会下降）
- 弊端
  - 和select一样，也需要轮询pollfd数组来获取就绪的描述符，随着连接增加性能线性下降
- 程序示例
  - [poll demo](/linux/socket/poll.c "poll demo")

**epoll**

- 函数
```c
      int epoll_create(int size);
        // 创建一个epoll file descriptor，size用来告诉内核这个监听的数目一共有多大
        // 参数size并不是限制了epoll所能监听的描述符最大个数，只是对内核初始分配内部数据结构的一个建议
        // Since Linux 2.6.8, the size argument is  ignored, but must be greater than zero. 因为现在是动态分配了，大于0是为了兼容老版本的
      int epoll_ctl(int epfd, int op, int fd, struct epoll_event *event);
        // 对指定描述符fd执行op操作
        // op：表示op操作，用三个宏来表示：
        //  添加EPOLL_CTL_ADD
        //  删除EPOLL_CTL_DEL
        //  修改EPOLL_CTL_MOD；分别添加、删除和修改对fd的监听事件
        // fd：是需要监听的fd（文件描述符）
        // epoll_event：是告诉内核需要监听什么事，
        // epfd：是epoll_create()的返回值
      int epoll_wait(int epfd, struct epoll_event *events, int maxevents, int timeout);
        // wait for an I/O event on an epoll file descriptor，最多返回maxevents个事件
        // 参数events用来从内核得到事件的集合，maxevents告之内核这个events有多大，这个maxevents的值不能大于创建epoll_create()时的size
        // 参数timeout是超时时间(毫秒，0会立即返回，-1永久阻塞）
        // 该函数返回需要处理的事件数目，如返回0表示已超时，-1表示异常
      struct epoll_event {
        uint32_t     events;      // Epoll events, a bit set
        epoll_data_t data;        // User data variable
      };
      typedef union epoll_data {
        void        *ptr;
        int          fd;
        uint32_t     u32;
        uint64_t     u64;
      }epoll_data_t;
```

- 特点
  - 相对于 select 和poll 来说，epoll 更加灵活，没有描述符限制. 上限是最大可以打开文件的数目，远大于2048
  - epoll 不同于 select 和 poll 轮询的方式，他是把所有就绪的通过一个结构体数组返回，所以我们可以精确的拿到所有就绪的描述符
  - 因为 epoll 可以精确返回就绪的描述符集合，所以 IO 的效率不会随着监视fd的数量的增长而下降. O(k)级，k为就绪描述符个数

- 工作模式
  - LT模式  
      LT(level triggered)是缺省的工作方式，并且同时支持 blocking 和 non-blocking socket  
      在这种做法中，内核告诉你一个文件描述符是否就绪了，然后你可以对这个就绪的fd进行IO操作. 如果你不作任何操作，内核还是会继续通知你的
  - ET模式  
      ET(edge-triggered)是高速工作方式，只支持non-blocking socket. 在这种模式下，当描述符从未就绪变为就绪时，内核通过epoll告诉你  
      然后它会假设你知道文件描述符已经就绪，并且不会再为那个文件描述符发送更多的就绪通知(only once)，直到你做了某些操作导致那个文件描述符不再为就绪状态  
- 程序示例
  - [epoll_et demo](/linux/socket/epoll_et.c "epoll_et demo")
  - [epoll_lt demo](/linux/socket/epoll_lt.c "epoll_lt demo")


