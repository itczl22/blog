### 正则表达式与通配符

正则表达式这个概念最初是由Unix中的工具软件（例如sed和grep）普及开的, 正则表达式通常缩写成"regexp". 正则表达式用来在文件中匹配符合条件的`数据`的, 正则是`包含匹配` , grep、awk、sed等命令是操作字符串, 所以支持正则表达式. 通配符是用来匹配符合条件的文件名的, 通配符是完全匹配, find、ls、cp等命令是操作文件的不支持正则表达式, 所以只能用shell的通配符进行匹配了.  所有的语言都有正则表达式, 但是只有极少的语言有通配符

* 正则表达式的分类
  * 基本的正则表达式（Basic Regular Expression 又叫 Basic RegEx  简称 BREs）
  * 扩展的正则表达式（Extended Regular Expression 又叫 Extended RegEx 简称 EREs）
  * Perl 的正则表达式（Perl Regular Expression 又叫 Perl RegEx 简称 PREs）

* linux常用工具支持的表达式类型
  * grep 支持：BREs、EREs、PREs
  * sed   支持：BREs、EREs
  * awk 支持：EREs

* 正则匹配规则

  |元字符    |   作用 |
  |:-----   |:----|
  | *       | 前一个字符匹配0次或者任意次|
  | .       | 匹配除了换行符外任意一个字符|
  | ^       | 匹配首行, 例如 ^world 会匹配以world开头的行|
  | $       | 匹配行尾, 例如 hello$ 会匹配以hello结尾的行|
  | []      | 匹配中括号中指定的任意一个字符, 只匹配一个. 例如:[0-9] 匹配任意一个数字, [0-9][a-z]匹配一个数字一个小写字母|
  | [^]     | 匹配除了中括号内以外的任意一个字符. [^0-9] 匹配除了数字之外的任意一个字符|
  | \       | 转义字符, 用于取消特殊符号的含义|
  | \{n\}   | 表示前面的字符恰好出现 n 次. 比如: [0-9]\{4\} 匹配4位数字|
  | \{n,\}] | 表示前面出现的字符不少于 n 次. 例如: [0-9]\{2,\} 表示两位及两位以上的数字|
  | \{n,m\} | 表示前面的字符至少出现 n 次 至多出现 m 次. 比如: [a-z]\{4,6\} 匹配4到6位小写字母|
  | +      | 表示前面的字符出现 1 次或者 多次|

  因为正则表达式是包含匹配，所以a\{n\}   a\{n,\}    a\{n,m\}匹配的结果有可能相同


### xargs

xargs 作用是将参数列表转换成小块分段传递给其他命令，以避免参数列表过长的问题，把上一步执行的结果作为下一个命令的参数。 即构造参数列表并运行命令

xargs的默认命令是echo, 空格和回车是默认定界符.

格式
* xargs [options] command

常用 options

* -t 表示先打印命令再执行

* -p 打印命令并询问是否执行

* -a 从文件读取而非stdin  
`xargs -a test.txt echo`

* -I 将xargs的每一项分别赋给-I指定的表示符, -I必须指定替换字符, 此标识符可以随意指定比如[]、123、最好用{}  
`ls *.txt | xargs -t -I{} mv {} {}.bak`

* -nX 每个命令行最多用X个参数, 即把上一个命令的结果按每行X个拆分  
`ls *.txt | xargs -n1`   
每行用一个参数, 结果就会显示为每行一个, 10个文件就会显示为10行, 否则默认显示一行

* -dY 指定分隔符为'Y'字符, xargs默认的分隔符是空格和回车, 一般和-n配合使用  
`cat 1.txt | xargs -dX -n1`  
用输入参数中的 'X' 字符做分隔符, 每个命令行使用一个参数

* -0 将 '\0' 作为定界符，等价于-d'\0'  
`find . -type f -name "*.txt" -print0 | xargs -0 wc -l` => `find . -type f -name "*.txt" -print |xargs wc -l`

* -r no-run-if-empty 当xargs的输入为空的时候则停止xargs, 不用再去执行了  
`echo ""|xargs -r  mv 1.txt`

* -E flag flag必须是一个以空格分隔的标志, 当xargs分析到含有flag这个标志的时候就停止处理后边的args  
`echo "123 456 atest test" > test.txt; cat test.txt | xargs -E "atest" echo => 123 456`   
此例flag为atest, atest的前后都得有空格才算匹配flag

* -s 命令行的最大字符数, 指的是xargs后面那个命令及其参数的最大命令行字符数, 不包括xargs的参数哦  
`cat test.txt | xargs -n1  -s 28 echo` echo   
及其要输出的内容不能大于28字节否则停止执行

### awk

* 命令格式  
awk '条件1{动作1}条件2{动作2}...' 文件名

* 执行顺序
先执行BEGIN ,  然后读取文件 , 读入一条记录(由RS指定, 默认是换行) , 然后将记录按指定的域分隔符划分域
$0则表示所有域, $1表示第一个域, $n表示第n个域, 随后开始执行模式所对应的动作action
接着开始读入第二条记录······直到所有的记录都读完, 最后执行END操作

* awk的一些内建变量

  | 变量     |   作用 |
  | :-----   | :---- |
  | $0      | 当前记录, 这个变量存放着整个行的内容|
  | $1-$n   | 当前记录的第 n 个字段, 字段间由 FS 分割|
  | NF      | 当前记录中的字段个数, 就是有多少列, number fields|
  | NR      | 已经读出的记录数, 就是行号, 从1开始, 如果有多个文件的话, 这个值会不断累加的, number read|
  | FNR     | 当前记录数, 与 NR 不同的是, 这个值是各个文件自己的行数|
  | FS      | 输入字段分隔符, 默认是空格或者tab, field separator |
  | OFS     | 输出字段的分隔符, 默认也是空格, output field separator|
  | RS      | 输入的记录分隔符, 默认是换行符, record separator|
  | ORS     | 输出的记录分隔符, 默认是换行符, output record separator|
  | FILENAME | 当前输入文件的名字|

* 內建变量的使用

  * `awk  'BEGIN{FS=":"}  {print $1,$3,$6}’  /etc/passwd`   
    以':'作为分隔符, FS支持正则, 比如 FS="[: ]"表示以冒号或者空格作为分隔符

  * `awk -F"[@ /t]" '{print $2,$3}'  test.txt`    
    以@或空格或Tab键分割test.txt文件的每一行, 并输出第二、第三列

  * `awk 'BEGIN{FS=":"} END{print NF}' /etc/passwd`   
    被':'分割为NF个字段

  * `awk '$3==0 && $6=="ESTABLISHED" || NR==1 {printf("%02s %s %-20s %-20s %s\n",NR, FNR, $4,$5,$6)}'  netstat.txt`

  * `awk  -F: '{print $1,$3,$6}' OFS="\t" /etc/passwd`   
    输出时用'\t'做间隔符


* 正则匹配

  * ~ 表示匹配

  * !~ 表示不匹配, 正则表达式必须放到 /.../ 里边

  * `awk '$6 ~ /ESTAB/ || NR==1 {print NR,$4,$5,$6}' OFS="\t" test.txt`    
    如果$6和ESTAB匹配或者是首行时, 以 \t 做为间隔打印行号以及4、5、6列

  * `awk '$6 !~ /ESTAB/ || NR==1 {print NR,$4,$5,$6}' OFS="\t" test.txt`

  * `awk  '/LISTE/'   test.txt`   
    当前行中有LISTEN，和grep一样可以去匹配一行

  * `awk  '!/LISTE/'  test.txt`   
    当前行中不含LISTE的, grep -v

* 用awk拆分文件

  * `awk 'NR!=1 {print > $6}' test.txt`   
    不处理表头, 根据默认分隔符拆分并输出到$6命名的文件中

  * `awk 'NR!=1{print $2,$4 > $6}' test.txt`  
    拆分后输出指定字段

* awk中的if和for

  * `awk 'NR !=1 {if($6 ~ /TIME|ESTABLISHED/) print > "e.txt"; else if($6 ~ /LISTEN/) print > "l.txt"; else print > "o.txt" }' netstat.txt`  
    把各种连接状态分文件存储

  * `awk 'NR !=1 {a[$6]++;} END {for (i in a) print i ", " a[i];}'  netstat.txt`  
    统计每种状态出现的次数

* awk处理字符串

  * 字符串连接用双引号"": `awk 'BEGIN{a="100";b="10test10";print a""b"""end";}'` -> 10010test10end

  * 字符串转数字: `awk 'BEGIN{a="100";b="10test10";print c=a+b;}'`  -> 110.  
    所以想获取'124test'的数字部分巧用printf的%d可以实现

  * 数字转字符串: `awk 'BEGIN{a=100; b=200;c=a""b"""300";print c}'` -> 100200300

* 注意
awk中如果一个字段值为0或者空，==null都是成立的，==0只对值为0的成立

* 示例

  * `df -h | awk  '{printf $2 "\t" $4 "\n"}'`  
    提取第二列和第四列  默认使用空格或者tab作为分隔符

  * `df -h | grep "/dev/sda1" | awk '{print $5}'  |  cut  -d  "%" -f 1`

  * `df -h | awk  'BEGIN{print "test"} {print $2 "\t" $4}'`  
    在读取内容前先执行begin后的{ } 即print "test"

  * `df -h | awk  'END{print "test"} {print $2 "\t" $4}'`    
    在结尾处执行print "test"

  * `awk '{print $30, $31, $41, $42}' $file1 $file2 $file3 $file4 | awk 'BEGIN{FS="[= ]"}{ftm+=$2; ptm+=$6} END{print ftm, ptm}'`   
    用=和空格做分隔符

  * `cat /etc/passwd | grep "/bin/bash" | awk '{FS=":"} {print $1 "\t" $3}'`

  * `cat /etc/passwd | grep "/bin/bash" | awk 'BEGIN{FS=":"} {print $1 "\t" $3}'`  
    比较上边这两句的不同, 原因很简单, 因为:awk的执行过程是, 先把第一行内容读取出来放入$0中, 拆分后放入到相应的$x中, 然后再去执行后边的print,如此循环
    * 对于第一个而言他已经拆分好了，然后再去执行print时才发现FS=":"，已经晚了。读取第二行的时候就正常了
    * 对于第二个而言加了BEGIN表示在读取之前先执行BEGIN后边{ }里边的内容，然后再打印

  * `ls /logs/stats-2015-09-02* | awk -F '_' '{i=NF; printf("%d\n", $i);}' | sort -n | tail -5`

  * 选出当前文件以01:02:03开头且包含xxx的行  
    `for f in *; do awk '/^01:02:03/{} /^01:1/{exit}' $f |grep xxx; done`  
    `for f in *; do sed -n '/^01:02:03/p; /^01:1/q' $f |grep xxx; done`

  * 对test文件的以a-c开头的行的第二列求和  
    `awk '/^[a-c]/ {sum += $2};END {print sum}' test`

  * 删除偶数行  
    `awk 'NR%2==1{print}' a.txt   > tmp.txt`     
    只打印奇数行，对于偶数行同理用NR%2==0

### sed

__格式__

* sed 选项 '动作' 文件名


__常用正则表达式__

* ^ 表示一行的开头. 如: /^#/ 以#开头的匹配

* $ 表示一行的结尾. 如: /}$/ 以}结尾的匹配

* \< 表示词首. 如 \<abc 表示以 abc 为首的词

* \> 表示词尾. 如 abc\> 表示以 abc 結尾的词

* . 表示任何单个字符

* \* 表示某个字符出现了0次或多次

* \[\] 字符集合. 如: [abc]表示匹配a或b或c, 还有[a-zA-Z]表示匹配所有的26个字符, 如果其中有^表示反, 如[^a]表示非a的字符

* 正在表达式必须放到 /.../ 之间

__选项__

* -n: 一般sed命令会把所有数据都输到屏幕, -n只会把sed处理过的行输到屏幕

* -e: 允许对输入数据应用多条sed命令编辑, 即允许多个动作, 用封号分割开

* -i: 不加-i修改后源文件不变, 只是把结果显示在屏幕, 加上-i 用sed的修改结果直接修改读取数据的文件, 而不是由屏幕输出

__动作__

* s: 字符替换, 用一个字符串替换另一个字符串
  格式为 "行范围s/旧字串/新字串/g", 和vi一样
  ```
  sed -i‘4s/70/100/g'  file   # 把第4行的70全部替换为100,连文件中的也替换
  sed '3,6s/70/100/g'  file   # 只替换3-6行的
  sed 's/a/A/1'  file         # 替换每一行的第一个a为A
  sed 's/a/A/2'  file         # 每一行的第二个a为A
  sed 's/a/A/3g' file         # 每一行的第三个a之后全换
  多个匹配示例:
      sed '1,3s/my/your/g; 3,$s/This/That/g' file
      等价于 sed -e '1,3s/my/your/g' -e '3,$s/This/That/g' file  # 1-3行的, 3-结尾的
  我们可以使用&来当做被匹配的变量:
      sed 's/my/[&]/g' file  # & 的值就是 my
  ```
    
* N: 把下一行内容纳入当前行进行匹配,即两行合在一起做一次匹配
  ```
  sed 'N; s/my/your/' file  # 把两行作为一行匹配,合并后的后第一个my替换为your
  sed 'N;s/\n/,/' file      # 每两行合并为一行, 用逗号分隔开
  ```

* a: 追加, 在当前行后添加一行或多行
  ```
  sed '2a nidaye' file        # 第二行后加一行nidaye
  sed "$ a This is my monkey, my monkey's name is wukong" file        # 最后一行追加
  sed "/fish/a This is my monkey, my monkey's name is wukong" file    # 匹配到fish就追加
  ```

* i: 插入, 在当前行前插入一行或多行
  ```
  sed '2i nidaye' file      # 第二行前加一行nidaye
  sed "i This is my monkey, my monkey's name is wukong" file    # 每行之前都会插入
  ```

* c: 行替换, 用c后面的字符串替换元数据行
  ```
  sed '4c  一边呆去'  file     # 把第四行替换为"一边呆去"
  sed "/fish/c that is this" file  # 匹配到fish就替换
  ```

* d: 删除, 删除指定的行
  ```
  sed '/fish/d' file      # 匹配到fish就删除当前行
  sed '3d' file           # 删除第三行
  sed '2,$d' file         # 把2-结尾全删了
  ```

* p: 打印, 输出指定的行,等价于grep命令
  ```
  sed -n '2p' file              # 只打印第二行
  sed -n '/cat/,/fish/p' file   # 从一个模式匹配到另一个模式匹配, 即从匹配到cat的行到匹配到fish的行全部大打印
  sed -n '1,/fish/p' file       # 从第一行打印到匹配fish成功的那一行
  ```

* 命令打包:
  ```
  sed '3,6 {/This/d}' file           # 对3行到第6行, 执行命令/This/d
  sed '3,6 {/This/{/fish/d}}' file   # 对3行到第6行, 匹配/This/成功后, 再匹配/fish/, 成功后执行d命令
  sed '1,${/This/d;s/^ *//g}' file   # 从第一行到最后一行, 如果匹配到This, 则删除之；如果前面有空格, 则去除空格
  ```

* 行数的表示:
  ```
  第三行:      3       sed '3d' file      # 删除第三行
  三到五行:    3-5     sed '3-5d' file    # 删除3-5行
  偶数行:      1~2     sed '1~2d' file    # 删除偶数行
  奇数行:      1-2!    sed '1~2!d 'file   # 删除奇数行
  first~step,  2~5     sed '2~5d' file    # will match every fifth line, starting with the second
  ```

* 示例
  ```
  sed 's/\[23\/Mar\/2016\://g' file    # 去掉[23/Mar/2016:
  sed -e 's/abc//g; s/def//g'  file    # 把所有的abc和def都替换为空, 多动作用封号分割
  cat test | sed 's/^[ \t]*//g'        # 把结果中开头的空格或tab替换为空即删除行首空格

  echo '<b>This</b> is what <span style="text-decoration: underline;">I</span> meant. Understand?' > a.txt
  sed 's/<.*>//g' a.txt     # 结果是  meant. Understand?
  sed 's/<[^>]*>//g' a.txt  # 结果是  This is what I meant. Understand?

  sed s/[[:space:]]//g      # 删除所有空格或tab
  ```
  
  
### cut 字符截取命令

cut是列提取, grep是行提取

__命令格式__
* cut [选项] 文件名

__参数__
* -d 分隔符   按照指定分隔符分割列, 默认的分隔符是tab  
  `cut -f 1,3 -d ":" /etc/passwd   # 用冒号做分隔符`

* -f 列号[,列号]  提取第几列  
  `cut -f 1,3 -d ":" /etc/passwd   # 列出第一列和第三列`

* -c 字符
  ```
  cut -c  -6   /etc/passwd       # 取前6个字符
  cut -c  1-3  /etc/passwd       # 取第1到第3(包含1,3)个字符
  cut -c  5-   /etc/passwd       # 打印第5个到结尾的字符
  ```
    
* -b 字节  
  `cut -c -6 file`               # 打印前6个字节，对于英文和-c一样，对中文不一样，一般用-c

* -complement  过滤  
  `cut -f 2,4 -d ":" /etc/passwd --complement`   # 不打印2,4列

* --output-delimiter=<字段分隔符>：指定输出内容是的字段分割符  
  `cut -f 2,4,6 -d ":" --output-delimiter="  " /etc/passwd`   # 打印2,4,6列用"  "间隔开
    
### sort 字符串排序命令

sort将文件的每一行作为一个单位, 相互比较, 比较原则是从首字符向后, 依次按ASCII码值进行比较, 最后将他们按升序输出

__格式__

* sort  选项  文件名

__选项__

* -f: 忽略大小写, 全按大写字母比较

* -n: 以数值型进行排序, 默认使用字符串型排序

* -r : 反向排序, 大到小

* -t : 指定分隔符, 默认是制表符

* -u: 在输出行中去除重复行

* -o: 把排序后的内容输入到源文件中, 注意此时不能重定向(因为他会清空源文件) sort -or number.txt

* -b: 会忽略每一行前面的所有空白部分, 从第一个可见字符开始比较

* -k n [,m]: 按照指定的字段范围排序, 从第n字段开始, 第m字段结束（默认到行尾）

__示例__

```
sort  -r  /etc/passwd               倒叙打印
sort -n -t ":" -k 3,3 /etc/passwd   用: 做分隔符, 用第三个字段作为排序标准, 用数值排序, 否则2比142大
sort -u t.txt -ot.txt               排序去重后写入源文件, 不能使用重定向！
```

### grep: Global Regular Expression Print
grep 用来查找内容

__格式__
* grep [参数] target file

__参数__

* -n: 显示所在行行号

* -r: 递归, 如果grep的目标是目录可以使用该参数, 等价于-d

* -v: 过滤掉匹配的  
  grep -v "test" *     查找不包含test的行

* -E: 把-E后边的字符串作为正则来匹配  
  grep -E '2015/07/29 12:1[0-5]'

* -i: 忽略大小写的不同, 所以大小写视为相同

* -l: 只输出包含匹配字符的文件名  
grep -l usr /etc/passwd  => /etc/passwd

* -L: 只输出不匹配的文件名

* -c: 计算找到 '搜寻字符串' 的次数  
grep root -c /etc/passwd

* -h: 查询多文件时不显示文件名

* --color=auto: 可以将找到的关键词部分加上颜色的显示, 默认就是

* -p: 表示使用perl的正则表达式, 主要用于过滤目标数据  
获取ip地址: ifconfig eth0 | grep -oP "(?<=inet )[^ ]+"      ?<=表示向后查找且不包含

* \<: 从匹配正则表达式的行开始  
  grep  -rn  '\<root' /etc/passwd  查找以root开头的的行

* \>: 到匹配正则表达式的行结束  
  grep  -rn  'txt\>'   查找以txt结尾的行

* -o: only-matching 和-E结合使用表示仅仅显示匹配的东西   
grep -oE '"aid":[1-9]*' tmp.txt  
grep -rnE '*.txt|abc'  

* 示例  
grep -v '^$' data.txt 过滤掉空行  
grep -hrE 'root|server|' ./log/* | wc -l

### wc 字符统计命令
如果不加任何参数默认统计 行数、单词熟、字节数

__格式__

* wc  选项   文件名

__选项__

* -l: 只统计行数

* -w: 只统计单词数

* -m: 只统计字符数

* -c: 只统计字节数

* -L: 打印最长一行的长度

__示例__

```
wc   /etc/passwd     结果依次是 行数，单词数，字符数
wc -w /etc/passwd
```

### uniq

删除文件中的重复行；重复的行一定相邻 (在发出 uniq 命令之前，请使用 sort 命令使所有重复行相邻)

__格式__

* uniq [选项] 文件

__参数__

* -c 在输出行前面加上每行在输入文件中出现的次数

* -d 仅显示重复行

* -u 仅显示不重复的行

* -i 忽略大小写

* -w n compare no more than N characters in lines

### find

目录内查找, 支持更多参数辅助

__格式__

* find option path expression

__参数__

* -size: 查找指定大小的文件  
find .  -size  +100k     在当前目录下查找大于100k的文件

* -type 指定文件类型:b c d p f  l s  
find . -type f 当前目录递归查找文件

* -name 根据文件名查  
find . -name "[A-Z]*[4-9].log"  当前目录下查找以大写字母开头以4-9.log结尾的文件

* -perm 根据文件权限查  
find . -perm 0644

* -ctime +|- 天数   +表示指定天数之前修改的, -表示指定天数之内修改的  
find . -ctime +10

* -mtime +|- 天数   +指定天数之前创建的, -表示指定天数之内创建的  
find . -mtime +10

* -path  path  用来判断当前搜索到的是不是path, 是执行后边的操作, 长和-prune配合使用  
find . -path "./test"  看当前是不是搜索到./test这个目录,

* -prune     忽略的路径参数必须紧跟着搜索的路径之后, 否则该参数无法起作用。同时使用-depth则该参数将不生效  
find . -path "./test" -prune -o -name "*.txt" -print   当前目录下根据名字查*.txt文件, 如果匹配到./test则跨过该目录

* -maxdepth  查找的最大路径深度  
find . -maxdepth 2 -name "test.log" 最大深入两层目录

* -mindepth  查找的最小路径深度  
find . -mindepth 2 -name "test.log" 只处理大于两层目录的

* -print0    打印查找的结果, 并用 '\0' 做分隔符  
find *.py -print0     所有的py文件打印在一行并以'\0'分隔

* -print     将匹配的文件输出到标准输出, 用 '\n' 做分隔符  

* -exec      对匹配的文件执行该参数所给出的shell命令. 命令的格式为 `cmd '{}'  ';'    注意 '{}' 和 ';' 之间的空格  
find . -mtime -10  -exec mv '{}' '{}'.bak ';'

* -ok  和-exec一样, 只是每次执行命令前会给出提示

* -depth     从指定目录下最深层的子目录开始查找

* -user      按属主查

* -group     按属组查

* 这几种方式也可以混合使用  
find / -name "abc" -type f


__示例__

```
find .  会显示当前目录的所有文件, 而且会递归子目录
find . | grep .txt 查找当前目录内所有txt文件
find /logs/pvlog/ -maxdepth 1 -mindepth 1 -mtime +30 | xargs -I{} rm -rf {}    后边等价于 xargs rm  -rf , 删除30天之前创建的文件
find . -type f -exec ls -l '{}' ';'  查找并对结果执行ls -l 命令； '{}'代表找到的内容
find / -type f -exec grep -ni  hello  '{}'  ';'  -print 在根目录下找文件, 并在文件中查找hello, -n表示打印行号, -i表示忽略大小写
```
