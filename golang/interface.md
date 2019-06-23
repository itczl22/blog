An interface type is defined as a set of method signatures.  

实现了interface的method就实现了该interface，不需要特定的使用什么implement关键字之类的。可以解耦interface的声明和实现.  

A value of interface type can hold any value that implements those methods. 必须是实现了接口的方法才可以赋值给该接口变量.  

如果T实现了某接口的方法但是*T没有实现，那么只能把T赋值给该接口变量，\*T是不可以的.  

#### interface value  

* Calling a method on an interface value executes the method of the same name on its underlying type(多态)

* 如果一个interface持有的具体值是nil, 那么interface变量本身并不是nil. 此时用该interface变量调用接口中某个方法时并不报错, 不像其他语言会抛出空指针异常, 只不过为nil时需要在具体实现方法时指定为nil的处理逻辑，不然运行时会报无效内存地址

* nil interface value  
var i I     只声明了一个nil的interface I，既不持有value也不持有type  
Calling a method on a nil interface is a run-time error，因为它内部没有一个类型可以标明具体调用哪个方法  

* empty interface  
var i interface{}  
An empty interface may hold values of any type，因为任何类型最低都实现了0个方法，主要用来处理不知道是什么类型的情况  

* interface values with nil underlying values、nil interface value 、empty interface  
interface values with nil underlying values是定义了一个interface指向一个实现该interface的type, 但是type类型的变量没有值.可以运行但是要指定为nil的处理逻辑  
nil interface value是声明了一个interface变量但是没给赋值，直接用这个空的interface变量调用interface里边的method会报运行时错误  
empty interface是interface本身就是空的里边没有任何方法，用来存储任意类型的值  

* 接口数组
 接口数组里边可以存放任何类型的数据  
 i := []interface{}{"OK", 9, []int{108, 117, 101, 51, 97, 100, 10}}  

#### 接口示例

* String
```
  type String interface {
    String() string
  }
  String是fmt包中定义的接口, fmt.Println在输出时会自动调用，所以想对一个自定义的类型调用fmt.println函数只需要实现String()这个method
  func (ip IPAddr) String() string {
    return fmt.Sprintf("%d.%d.%d.%d", ip[0], ip[1], ip[2], ip[3])
  }
```

* error
```
  type error interface {
    Error() string
  }
  error也是内建type, 当一个method返回类型为error时, 在返回Error的receiver时自动调用. 有错就返回Error的receiver，没错就返回nil

  func (e *MyError) Error() string {
    return fmt.Sprintf("at %v, %s", e.When, e.What)
  }

  程序返回error的几种方法：
  errors.New("KafkaBrokers is requred”) 如果错误信息就是一个字符串，那么用这种方法
  fmt.Errorf("config.Parse json: %v", err) 如果错误信息需要格式化，比如需要将一些额外变量里边的信息也格式化到错误信息中
  func (e *MyError) Error() string { return fmt.Sprintf("at %v, %s", e.When, e.What) } 把类型&MyError当做error处理
```

* io.Reader
```
  type Reader interface {
    Read(p []byte) (n int, err error)
  }
  任何类型只要实现了Read方法就可以通过io.Reader接口类型调用Read方法

  type Writer interface {
    Write(p []byte) (n int, err error)
  }

  type ReadWriter interface {
    Reader
    Writer
  }
```
