Both array and structs are fixed size. In contrast, slices and maps are dynamic data structures that grow as values are added.

#### array

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
r := [...]{99: 2}. define an array r with 100 elements, all zero except for the last which value is 2

#### slice
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

* s := []int{} 等价于 var s []int  都是空的slice。用后者因为前者已经分配大小，后者如果不使用就不会分配大小的
  
* slice operate
```
slice literals : 
    slice literal is like an array literal without the length
    q := []int{2, 3, 5, 7, 11, 13}
slice default ：
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
