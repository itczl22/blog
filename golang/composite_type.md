Both array and structs are fixed size. In contrast, slices and maps are dynamic data structures that grow as values are added.

### array

```
  var arr[6] int
  primes := [6]int{2, 3, 5, 7, 11, 13}
```     
* Array are rarely used directly in Go.  

* The element of a new array variable are initially set to the zero value for element type. like var arr [3]int

* The size of an array is part of its type, so [3]int and [4]int are different types and arrays cannot be resized.

* In an array literal, if an ellipsis "..." appears in place of the length, the array length is dertemined by the number of initializers. arr := [...]int{1,3,5}

* If an array\`s element type is comparable then the array type is comparable too, using == or != must be the same type. like [3]int with [3]int, [4]int is not.

* 数组也可以通过索引指定值，而且可以省略部分索引：symbol := […]string{1: "one", 2:"tow", 9:"nine"} 这样就定义了10个元算，其他都是""

* r := [...]{99: 2}. define an array r with 100 elements, all zero except for the last which value is 2

### slice
* The type []T is a slice with elements of type T.  A slice does not store any data, it just describes a section of an underlying array. slice has three components: a pointer, a length, a capacity. The pointer points to the first element of the array that is reachable through the slice. 因为slice包含有指向array的指针，所以slice是引用类型，而array不是
```
  type SliceHeader struct {
    Data uintptr
    Len  int
    Cap  int
  }
```

* Changing the elements of a slice modifies the corresponding elements of its underlying array. Other slices that share the same underlying array will see those changes.

* Slicing beyond cap(s) cause a panic, but slicing beyond len(s) extends the slice

* Slice literal implicitly creates an array variable of the right size and yields a slice that points to it.

* Unlike array, slice are not comparable, so we can\`t use == to test weather two slice contain the same elements.

* The zero value of a slice type is nil. A nil slice has no underlying array. can\`t use!

* The build-in function make create a slice, in fact, make create an unnamed array variable and return a slice of it.

* s := []int{} 等价于 var s []int  都是空的slice。用后者因为前者已经分配大小，后者如果不使用就不会分配大小的. 前者 != nil 后者 == nil.

* slice operate
```
 slice literals
    slice literal is like an array literal without the length
    q := []int{2, 3, 5, 7, 11, 13}

 slice default
    var a[10] int
    a[0:10]     a[:10]     a[0:]     a[:]

 slice length and capacity:
    The length of a slice is the number of elements it contains.
    The capacity of a slice is the number of elements in the underlying array, counting from the first element in the slice.  因为起始地址变了
    s := []int{2, 3, 5, 7, 11, 13}     len(s) ->6     cap(s) ->6
    s := s[:4]   len(s) ->4     cap(s) ->6     ［2，3，5，7］
    s := s[2:]   len(s) ->2     cap(s) ->4     ［5，7］

 delete element from slice :
    copy(slice[i:], slice[i+1:])
    append(slice[:i], slice[i+1:]...)

 nil slice：
    the zero value of a slice is nil
    var s []int     Println(s, len(s), cap(s)) ->[] 0 0
    A nil slice has a length and capacity of 0 and has no underlying array

 creating a slice with make:
    Slices can be created with the built-in make function; this is how you create dynamically-sized arrays.
    The make function allocates a zeroed array and returns a slice that refers to that array
    make(array, len, cap)     default cap equal to len
    b := make([]int, 0, 6) 【append时从0开始】    a := make([]int, 5)【append时从5开始】
    The make built-in function allocates and initializes an object of type slice, map, or chan (only).

 slice of slice :
    board := [][]string{
        []string{"_", "_", "_"},
        []string{"_", "_", "_"},
        []string{"_", "_", "_"},
    }

 append to slice：
    var s []int
    s = append(s, 2, 3, 4)         //len＝3，cap > 3      如果首次分配或者没有位置时进行分配，cap有可能大于你分配的长度，但是len是等于的
    if the backing array of s is too small to fit all the given values a bigger array will be allocated.
    the returned slice will point to the newly allocated array. 原来的s会被垃圾回收机制回收

 empty a slice：
    想要清空一个slice就重新make一下
    s = make([]int, 0 , 10) 如果作为参数传递必须使用指针，因为地址变了
```

### struct

* def
```
  type Vertex struct {
      X int
      Y int
  }
```

* struct array
```
  s := []struct {
    i int
    b bool
  }{
    {2, true},
    {3, false},
    {5, true},
    {7, true},
    {11, false},
    {13, true},
  }
```

* pointer to struct
```
  p := &v
  p.X = 110                         //不是(*p).x = 110 just for simple，当然这种写法也是对的
```

* struct literals
```
  p := Pointer{1,2}          only used for smaller struct which is an obvious field ordering convention
  p := Pointer{x: 1, y: 2}   this form is more used. the order of field doesn`t matter.

  If a field is omitted in second literal, it is set to the zero value for its type.
  Two form cannot be mixed in the same literal
```

* Consecutive fields of the same type may be combined,typically combining related fields

* The dot notation also works with a pointer to a struct

* A struct type may contain a mixture of exported and unexported fields

* An aggregate value cannot contain itself except a pointer type of which

* For efficiency, larger struct types are usually passed to or returned from function indirectly using a pointer.                

* If all fields of a struct is comparable, the struct itself is comparable. == or !=, 但必须是同一结构体类型 

* 结构体中成员默认值都是其零值, 因此对于零值为nil类型的成员必须要初始化否则不能使用

* struct embedding and anonymous fields  
Go let us declare a field with a type but no name, such field named anonymous fields. Example：
```
  type Circle struct {
    Point
    Radius  int
  }
```
 1. var c Circle
    * 此时如果要访问Point的x直接用c.X就可以等价于c.Point.X，即可以省略中间匿名的field name  
    
    * 但是literal不可以省略，即: c = Circle{1, 2, 3}是错的!! 必须是c = Circle{Point{1, 2}, 3} 或者 c = Circle{Point: Point{X:1, Y:2,}, Radius: 3}。注意：所有的逗号都是必须的  
 
 2. we cannot use two anonymous fields of the same type since their name would conflict.
    ```
    type Writer {
      io.Writer
      http.ResponseWriter
    }
    ```
    * io.Writer这个接口已经有 Write方法了，http.ResponseWriter 同样有 Write方法。那么对 g.Write写的时候，到底调用哪个呢？程序也不知道，编译就出错, 不过你可以重写g.Write方法指定调用哪一个
  
 3. 屏蔽和释放
    * 只要名称相同，无论这两个方法的签名是否一致，被嵌入类型的方法都会“屏蔽”掉嵌入字段的同名方法. 即使在两个同名的成员一个是字段, 另一个是方法的情况下, 这种"屏蔽"现象依然会存在
  
    * 不过即使被屏蔽了，我们仍然可以通过链式的选择表达式(a.b.c)，选择到嵌入字段的字段或方法
  
    * 我们可以通过类型变量的名称后跟"." 再后跟嵌入字段类型的方式引用到该字段. 也就是说嵌入字段的类型既是类型也是名称
  
    * 嵌入字段的方法集合会被无条件地合并进被嵌入类型的方法集合中
  
    * 如果处于同一个层级的多个嵌入字段拥有同名的字段或方法, 那么从被嵌入类型的值那里, 选择此名称的时候就会引发一个编译错误, 因为编译器无法确定被选择的成员到底是哪一个
  
### map
* def
```
  var m map[string]Vertex       // 不能使用，map必须要make
  m = make(map[string]Vertex)

  m["Bell Labs"] = Vertex{
    40.68433, -74.39967,        // don`t  foget last comma
  }
```

* map literal
```
  var m = map[string]Vertex {
    "Bell Labs": Vertex {
      40.68433, -74.39967,
    },
    "Google": Vertex {
      37.42202, -122.08408,
    },
  }
 ```

* If the top-level type is just a type name, you can omit it from the elements of the literal.
```
     var m = map[string]Vertex{
         "Bell Labs": {40.68433, -74.39967},
         "Google":    {37.42202, -122.08408},
     }
```

* mutating maps
```
  m[key] = elem
  elem = m[key]        // 如果key不存在，则返回key对应类型的零值
  elem, ok := m[key]   // If key is in m, ok is true. If not, ok is false;
                       // If key is not in the map, then elem is the zero value for the map's element type
  delete(m, key)
```

* The zero value of a map is nil. A nil map has no keys, nor can keys be added. append只能追加slice, delete只能删除map

* The make function returns a map of the given type, initialized and ready for use.

* The key type must be comparable using ==

* A map is reference to a hash table, nil的map不reference 任何 hash table；nil的slice不reference 任何array

* A map element is not a variable, and we cannot take its address:  _ = &ages["bob"]   //compile error, 因为随着元素的增加，原hash表不够用会重新rehash

* The order of map iteration is unspecified. To enumerate the key/value pairs in order, we must sort the keys explicitly using sort package

* 对于nil map的大部分操作都是安全的除了往里边存储数据。You must allocate the map before you store into it. so you can lookup/delete/len/range on a nil map。

* As with slice, map cannot be compared to each other; the only legal comparison is with nil. 只有array是可以比较的，如果要比较slice或者map必须用loop去循环比较

* ages := map[string]int{}  并不等价于 var ages map[string]int。前者何以存入数据，后者不可以

* set of strings  
  make(map[string]bool)

* set of slice  
  先把slice格式化成可比较的类型比如字符串，用其做key

* go里边map的key不支持哪些类型，为什么，key应该如何选择？
  * Go 语言字典的键类型不可以是函数类型、字典类型和切片类型  
  * 因为map是通过hash来实现的，Go 语言会用被查找键的哈希值与这些哈希值逐个对比，看看是否有相等的，如果有相等的，那就再用键值本身去对比一次(hash碰撞)  
  * 如果键类型的值之间无法判断相等，那么此时这个映射的过程就没办法继续下去了  
  * 最后，只有键的哈希值和键值都相等，才能说明查找到了匹配的键 - 元素对  
  * map的性能主要取决于 "把键值转换为哈希值" 以及 "把要查找的键值与哈希桶中的键值做对比"，求哈希和判等操作的速度越快，对应的类型就越适合作为键类型  
  * 以求哈希的操作为例，宽度越小的类型速度通常越快  
  * 对于布尔类型、整数类型、浮点数类型、复数类型和指针类型来说都是如此  
  * 对于字符串类型，由于它的宽度是不定的，所以要看它的值的具体长度，长度越短求哈希越快  
  * 对数组类型的值求哈希实际上是依次求得它的每个元素的哈希值并进行合并，所以速度就取决于它的元素类型以及它的长度  
  * 对结构体类型的值求哈希实际上就是对它的所有字段值求哈希并进行合并，所以关键在于它的各个字段的类型以及字段的数量  
  * 对于接口类型，具体的哈希算法，则由值的实际类型决定  


### json

* JSON is an encoding of JavaScript values—strings,numbers,booleans,arrays and objects as unicode text.

* JSON arrays are used to encode Go array or slices

* JSON objects are used to encode Go maps(with string keys) or structs.

* map是json里边的字段name不确定，value是同一类型；struct是json里边的字段name是固定的，value是不同类型. map也可以实现类似struct的效果：map[string]interface{}
  
* Marshal : 
  * Only exported fields are marshaled. 无论是marshal还是unmarshal，go的field必须都是大写的 , 小写的不会解析
  
  * field tag: 
  ```
  Year   int     `json:"released"`
  Color bool  `json:"color,omitempty"`
  ````
  * the first part of the json  field tag specifies an alternative JSON name (like:total_count) for the Go field(like:TotalCount)  
  * an additional option,omitempty, which indicates that no JSON output should be produced. If the field has zero value for its type or is otherwise empty. 比如bool类型变量为false时也不输出  
  * omitempy：有就输出没有(零值)就不输出  
  * string：可以把数字类型解析为string类型，但是14.50只能输出位14.5  
  * 短线-：忽略这个字段不解析  
  
* Unmarshal:
  * unmarshal可以选择json的部分字段，其他字段可以ignore.即selective！！！
  * json里边有的，go里边没有的则忽略
  * json里边没有的, go里边有的则赋初值零
  * associates JSON names with Go struct names during unmarshal is case-insensitive 即Number int 不注明 `json:"number"`时会转为number
  
