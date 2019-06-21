#### 指针  

指针限制  
  * Go的指针不能进行数学运算
  
  * 不同类型的指针不能相互转换
  
  * 不同类型的指针不能使用==或!=比较
  
  * 不同类型的指针变量不能相互赋值
  
不可寻址的类型  
  * 常量的值
  
  * 基本类型值的字面量
  
  * 算术操作的结果值
  
  * 对各种字面量的索引表达式和切片表达式的结果值
  
  * 不过有一个例外，对切片字面量的索引结果值却是可寻址的
  
  * 对字符串变量的索引表达式和切片表达式的结果值
  
  * 对字典变量的索引表达式的结果值
  
  * 函数字面量和方法字面量，以及对它们的调用表达式的结果值
  
  * 结构体字面量的字段值，也就是对结构体字面量的选择表达式的结果值
  
  * 类型转换表达式的结果值
  
  * 类型断言表达式的结果值
  
  * 接收表达式的结果值

特殊情况

  * 对切片字面量的索引结果值是可寻址的. 因为不论怎样每个切片值都会持有一个底层数组, 而这个底层数组中的每个元素值都是有一个确切的内存地址的.  
  
  * 对切片字面量的切片结果值却是不可寻址的, 因为切片表达式总会返回一个新的切片值, 而这个新的切片值在被赋给变量之前属于临时结果.  
  
  * 通过对字典类型的变量施加索引表达式, 得到的结果值不属于临时结果, 可是这样的值却是不可寻址的. 原因是字典中的每个键 - 元素对的存储位置都可能会变化, 而且这种变化外界是无法感知的.  


  常量的值总是会被存储到一个确切的内存区域中, 并且这种值肯定是不可变的. 基本类型值的字面量也是一样, 其实它们本就可以被视为常量, 只不过没有任何标识符可以代表它们罢了.  
  
  临时结果在我们把这种结果值赋给任何变量或常量之前, 即使能拿到它的内存地址也是没有任何意义的.  

  无法调用一个不可寻址值的指针方法  
  
Go 的指针是类型安全的，但它有很多限制， Go 还有非类型安全的指针， 这就是 unsafe 包提供的 unsafe.Pointer
它可以绕过 Go 语言的类型系统，直接操作内存。例如，一般我们不能操作一个结构体的未导出成员，但是通过 unsafe 包就能做到

#### unsafe.Pointer

```
  type ArbitraryType int
  type Pointer *ArbitraryType
```
可以看到unsafe.Pointer其实就是一个\*int, 一个通用型的指针. 类似于C语言里的void*指针  
unsafe.Pointer可以表示任何指向可寻址的值的指针, 同时它也是指针值和uintptr值之间的桥梁. 通过它可以在这两种值之上进行双向的转换.  

关于unsafe.Pointer的2个规则

  * 任何类型的指针和 unsafe.Pointer 可以相互转换

  * uintptr 类型和 unsafe.Pointer 可以相互转换

第一个个规则主要用于\*T1和\*T2之间的转换
  ```  
    i:= 10
    ip:=&i
    var fp *float64 = (*float64)(unsafe.Pointer(ip))
  ```

第二个规则用于操作内存地址  
  * \*T是不能计算偏移量的, 也不能进行计算, 但是uintptr可以, 所以我们可以把指针转为uintptr再进行偏移计算, 这样我们就可以访问特定的内存了, 达到对不同的内存读写的目的.
  ```
    u:=new(user)
    pName:=(*string)(unsafe.Pointer(u))
    *pName="张三"

    pAge:=(*int)(unsafe.Pointer(uintptr(unsafe.Pointer(u))+unsafe.Offsetof(u.age)))
    *pAge = 20

    type user struct {
      name string
      age int
    }
  ```
  * 第二个偏移的表达式非常长，但是也千万不要把他们分段, 这里会牵涉到GC, 如果我们的这些临时变量被GC, 那么导致的内存操作就错了, 我们最终操作的就不知道是哪块内存了
  
### unsafe.Pointer应用

string 和 slice 的相互转换
```
type StringHeader struct {
    Data uintptr
    Len int
}

type SliceHeader struct {
    Data uintptr
    Len int
    Cap int
}

func string2bytes(s string) [] byte {
    stringHeader := (*reflect.StringHeader)(unsafe.Pointer(&s)) 
    bh := reflect.SliceHeader {
        Data: stringHeader.Data,
        Len: stringHeader.Len,
        Cap: stringHeader.Len,
    }

    return *(*[]byte)(unsafe.Pointer(&bh)) 
}

func bytes2string(b [] byte) string {
    sliceHeader := (*reflect.SliceHeader)(unsafe.Pointer(&b))
    sh := reflect.StringHeader {
        Data: sliceHeader.Data,
        Len : sliceHeader.Len,
    }

    return *(*string)(unsafe.Pointer(&sh)) 
}
```
