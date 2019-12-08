* go语言是夸操作系统的, 他可以运行在各种系统下. Go runs on Unix-like systems—Linux, FreeBSD, OpenBSD, Mac OS X—and on Plan 9 and Microsoft Windows. Programs written in one of these environments generally work without modification on the others.

* go语言还实现了CSP(communicating sequential processes). In CSP, a program is a parallel composition of processes that have no shared state; the processes communicate and synchro- nize using channels. B

* go拥有自动垃圾回收、包系统、函数作为一等公民、词法作用域、系统调用接口、默认用UTF8编码的不可变字符串等. 它没有隐式的数值转换，没有构造函数和析构函数，没有运算符重载，没有默认参数，也没有继承，没有泛型，没有异常，没有宏，没有函数修饰，更没有线程局部存储

* 由于现代计算机是一个并行的机器, Go语言提供了基于CSP的并发特性支持. Go语言的动态栈使得轻量级线程goroutine的初始栈可以很小, 因此创建一个goroutine的代价很小, 创建百万级的goroutine完全是可行的

* go中方法不仅可以定义在结构体上, 而且可以 定义在任何用户自定义的类型上;并且具体类型和抽象类型(接口)之间的关系是隐式的
