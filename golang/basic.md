#### 类型
* 类型声明   
 1. type name underlying-type: type Celsius float64

 2. 一种新的类型，即使是和underlying-type也不能作比较和运算(必须显示转换)

 3. underlying-type 支持的运算新类型也支持哦，包括输出时的%T也可以匹配

 4. 一般用来重命名一些比较复杂的数据类型，这样书写比较方便

* 內建的类型别名  
在Go 1.9中, 内部其实使用了类型别名的特性. 比如内建的byte类型，其实是uint8的类型别名，而rune其实是int32的类型别名  
```
  type byte = uint8
  type rune = int32
```

* 类型别名主要作用  
类型别名的设计初衷是为了解决代码重构时，类型在包(package)之间转移时产生的问题  
比如我们有一个导出的类型flysnow.org/lib/T1，现在要迁移到另外一个package中, 比如flysnow.org/lib2/T1中  
没有type alias的时候我们这么做，就会导致其他第三方引用旧的package路径的代码，都要统一修改到lib2/T1，不然无法使用  
有了type alias就不一样了，类型T1的实现我们可以迁移到lib2下，同时我们在原来的lib下定义一个lib2下T1的别名`[type T1 = lib2.T1]`，这样已有的第三方的引用就可以不用修改也可以正常使用, 新的引用可以使用lib2/T1

* 类型别名和类型定义的区别  
我们基于一个类型创建一个新类型，称之为defintion；基于一个类型创建一个别名，称之为alias，这就是他们最大的区别。
```
  type MyInt1 int    // 类型定义
  type MyInt2 = int  // 类型别名
```

#### 基本数据类型

* integer  
 1. int有可能是32位也有可能是64位，即使同样的机器配置也有可能不同，具体和编译器有关

 2. 各种不同大小的integer类型属于不同的type、因此也需要显试的类型转换

 3. 一般使用int而非uint即使是长度大小之类的，比如i=len(a) 如果len返回uint，循环变量i就是uint，当其为0时再减就会变成很大的正数而非－1

* float  
 1. 一般使用float64，默认也是float64. 还有float32，没有float128哦

* boolean  
 1. 只有true和false两个值，其他类型的值不能直接作为Boolean类型进行条件判断，必须转换. 比如：i := 10; if i {}是不可以的，可以 if i != 0 {}

* string
 1. raw string : \`……\`  used to write regular expressions, HTML template, JSON literal, command usage, and mutiple line

 2. The i-th byte of a string is not necessarily the i-th character of a string. because of utf-8.

 3. no new memory allocated in both either case
    * Immutability means that it is safe for two copies of a string to share the same underlying memory.
    * A string s and a substring like s[7:] may safely share the same data.

 4. unicode  
    * assigns each one a standard number called a unicode code point or a rune in go terminology.
    * the natural data type to hold a single rune is int32, and that\`s what go uses and it has the synonym rune for precisely this purpose.
    * unicode每个字符都是int32，但是太浪费空间了，所以就有了utf-8.

 5. utf-8 :  
    * utf-8 is a variable-length encoding of unicode code point as bytes. it uses between 1 and 4 bytes to represent each rune, but only 1 byte for ASCII characters and only 2 or 3 bytes for most rune in common use. The high-order bits of the first byte of the encoding for a rune indicate how many bytes follow.  
    * Go source file are always encode in UTF-8.  
    * Unicode escapes : \uhhhh for 16-bit value, \Uhhhhhhhh for a 32-bit value(less use)  
     A rune whose value is less than 256 may be written with a single hexadecimal escape,such as ‘\x41’ for ‘A' but for higher values a \u or \U must be used.  
     ‘\xe4\xb8\x96’ is not a legal rune literal though these three bytes are a valid UTF-8 encoding of a single code point

 6. range 作用于string时自动decode bytes to rune  
    r := []rune(str)     string(r)  
    string(65)转换后对应的是数字65对应的unicode code point而不是”65”，是”A"

 7. strings and byte slices  
    Strings can be converted to byte slices and back again  
    To avoid conversions and unnecessary memory allocation, bytes.Buffer is used

* constants  
 1. The underlying type of every constant is a basic type : boolean, string, or number. 如: const timeout 100*time.Millisecond, 其中time.Duration的底层类型是int64所以可以声明常量

 2. 和变量的声明一样，可以指定类型，也可以不指定但是需要给初始值

 3. const name type = value。’type' and '= value’ can be omitted but not both

 4. The constant generator iota  
    A const declaration may use the constant  generator iota, which is used to create a sequence of related values without spelling out each one explicitly. constant often used to declare enums

 5. untyped constants  
    many constant are not committed to a particular type  
    就是说一个常量你不知道他的类型，比如：22，到底是int32还是int64，你不知道，所以叫untyped




####  类型转换

* 语法形式是T(x).  其中的x可以是一个变量, 也可以是一个代表值的字面量(比如0.22和struct{}), 还可以是一个M表达式. 如果是表达式那么该表达式的结果只能是一个值而不能是多个值.

* T(x)： convert value x to type T，只有拥有相同underlying-type的value才可以进行类型转换. 对于有相同underlying-type的类型转换并不改变他们的value只是改变type. 但是有的转换会改变值的，比如string和slice以及数字之间的转换，float -> int、string -> []byte

* 类型转换注意事项  

 * 对于整数类型值、整数常量之间的类型转换, 原则上只要源值在目标类型的可表示范围内就是合法的就行  
`var srcInt = int16(-255); dstInt := int8(srcInt)` 因为溢出，所以得到的dstInt是1

 * 虽然直接把一个整数值转换为一个string类型的值是可行的, 但被转换的整数值应该可以代表一个有效的 Unicode 代码点, 否则转换的结果将会是"?"（仅由高亮的问号组成的字符串值). 字符'?'的 Unicode 代码点是U+FFFD. 它是 Unicode 标准中定义的 Replacement Character, 专用于替换那些未知的、不被认可的以及无法展示的字符, 如`string(-1)`

 * 关于string类型与各种切片类型之间的互转

   1. 一个值在从string类型向[]byte类型转换时代表着以 UTF-8 编码的字符串会被拆分成零散、独立的字节. 除了与 ASCII 编码兼容的那部分字符集外, 以 UTF-8 编码的某个单一字节是无法代表一个字符的.  
   `string([]byte{'\xe4', '\xbd', '\xa0', '\xe5', '\xa5', '\xbd'}) // 你好`.  
   比如, UTF-8 编码的三个字节 \xe4、\xbd和\xa0 合在一起才能代表字符'你', 而 \xe5、\xa5和\xbd 合在一起才能代表字符'好'.

   2. 一个值在从string类型向[]rune类型转换时代表着字符串会被拆分成一个个 Unicode 字符.  
   `string([]rune{'\u4F60', '\u597D'}) // 你好`

#### 类型断言表达式
* 类型断言表达式的语法形式是x.(T), 其中的x代表要被判断类型的值. 这个值当下的类型必须是接口类型的,  当这里的x变量类型不是任何的接口类型时, 我们就需要先把它转成某个接口类型的  

* `value, ok := interface{}(container).([]string)` . 在赋值符号的右边, 是一个类型断言表达式. 它包括了用来把container变量的值转换为空接口值的interface{}(container). 以及一个用于判断前者的类型是否为切片类型 []string 的 .([]string)的断言表达式  

* 变量ok是布尔（bool）类型的, 它将代表类型判断的结果, true或false.  如果是true, 那么被判断的值将会被自动转换为[]string类型的值, 并赋给变量value, 否则value将被赋予nil（即“空”）. 这里的ok也可以没有, 但是这样的话当判断为否时就会引发panic

* 类型断言的两种方法  
```
  value, ok := interface{}(container).([]string)
```
```
  func getType(containerI interface{}) (elem string, err error) {
	switch t := containerI.(type) { // element.(type)语法不能在switch外的任何逻辑里面使用，如果你要在switch外面判断一个类型就使用第一种方式i.(T)
	case []string:
		elem = t[1]
	case map[int]string:
		elem = t[1]
	default:
		err = fmt.Errorf("unsupported container type: %T", containerI)
	}
	return
  }
```

#### 变量

* 变量的声明
```
  var name type = expression // type 和 = expression可以省略, 但是不能同时省略
  name := "test"             // 短变量声明, 只用于局部变量的声明

  := is a type of short variable declaration , declares one or more variables and gives them appropriate types based on the initializer   values.
  := is used only within a function, not for package-level variables.
```

* 变量重声明  
只针对短变量声明
```
  var err error
  n, err := io.WriteString(os.Stdout, "Hello, World!\n") // 这里对`err`进行了重声明
```

* 变量赋值  
x, y = y, x；先评估右侧的值，再赋值给左侧

* 变量细节  
 1. go里边没有未初始化的变量, var s string 此时s == "". go有个zero-value mechanism  

 2. package level的变量在main执行之前就被初始化了，local变量只有在函数执行的时候初始化  

 3. var一般是用来声明一个需要在后续赋值的变量、或者他的初始值不重要、或者他的类型和初始值的默认类型不一样(var i int64 = 10, 10默认是int不是int64)  

 4. := 是一个declaration，= 是一个assignment. 短变量声明时必须有一个是new variable  

 5. 对于引用类型的copy只会创建别名，并不会产生新的变量  

 6. new(T)和 &T是一样的，每次new的地址都是不一样的，除非new的大小是0，比如：struct{}、[0]int  

#### printf输出格式
```
%x,%d、%o,%b   16、10、8、2进制
%f,%g,%e       5位小数、输出所有位数、科学计数法
%t             true、false
%c             rune  
%s             string
％15.12s       占15个位, 最多输出12个字符
%.12s          最多输出12个字符, 多余的扔掉
%*s            就是“%15s”的扩展, 占不确定的位宽即*, fmt.Printf(“%*s\n”, depth*2, “iii”)输出iii占depth*2位宽
%q             "string"、'r' of rune    
%v             any value of natural format
%+v            输出结构体时会加上字段名, 如{Name:name Order:order}而％v是{name order}
%#v            以go语法的结果输出对象
%T             type of any value
%U             输出单个字符的Unicode code point, output like: fmt.Printf(“%U\n”,’A’) -> U+0433
%#o, %#x       输出时会带上0或者0x表示八进制或者十六进制
%[1]d          输出第一个数, 其他类型一样
% x            空一个，输出时16进制数之间有个空格, 其他类型也一样只不过没有必要
％8b           打印二进制为8位，不足自动补0
％8.3f         3位小数
%-5d           占5个位，左对齐
％5d           占5个位，右对齐. 如果结果有6位就输出6位, 意思只对<=5管用, 下边也一样
%%             输出%
```

#### go的25个关键字
```
  break、default、func、interface、select、case、defer、go
  map、struct、chan、else、goto、package、switch、const、var
  if、range、type、continue、for、import、return、fallthrough

  不能被用作name，其他的都可以被覆盖，比如内建函数、iota、true、nil、int、make等
```

#### 预声明的name
* constants  
```
  true false iota nil
```

* types
```
  int int8 int16 int32 int64
  uint uint8 uint16 uint32 uint64 uintptr float32 float64
  bool byte rune string error
  complex128 complex64
  go只有显式类型转换，int32转int64也必须显式转换
```

* functions
```
  make len cap new append copy close delete
  complex real imag panic recover
  These names are not reserved, so you may use them in declarations，为了避免混淆一般不覆盖
```

#### 小片段
* 任何类型的值都可以很方便地被转换成空接口  

* 一对不包裹任何东西的花括号, 除了可以代表空的代码块之外, 还可以用于表示不包含任何内容的数据结构(或者说数据类型). 比如struct{}, 它就代表了不包含任何字段和方法的、空的结构体类型. 而空接口interface{}则代表了不包含任何方法定义的、空的接口类型. 对于一些集合类的数据类型来说, {}还可以用来表示其值不包含任何元素, 比如空的切片值[]string{}, 以及空的字典值map[int]string{}

* 如果导入没有使用的包，或者需要的包没有导入都会导致编译出错

* go不需要封号，除非把多行写在一行上, 编译的时候换行会被替换成封号，函数的{必须写在同一行表示函数的声明结束

* All indexing in go use half-open intervals that include the first index but exclude last, 即包左不包右

* os.Args[0]代表的是命令本身，os.Args[1:]是参数

* We describe each package in a comment immediately preceding its package declaration[没空行], for a main package, this comment is one or more complete sentences that describe the program as  a whole

* 变量只声明未给初始值，那么默认使用该类型的0值初始化，var s string 那么s == “"

* go只要后++和后--，没有前++和前--，i++是语句不是表达式，因此 s := i++是错的，只能是i++

* s += sep，+= statement makes a new string, old contents of s are no longer in use ,so they will be garbage-collected in due course

* A map is reference to the data structure created by make, map的key是无序的，之所以这么设计师为了避免程序依赖这种特定的顺序

* It`s not a problem if the map doesn`t yet contain that key ，取对应类型的zero value

* the value of a constant must be a number, string or boolean
