#### Go 包管理的演变历史

__GOPATH__
* 在Go1.5之前使用GOPATH这个系统环境变量来决定包的位置. 

* GOPATH 解决了 **第三方源码依赖** 的问题, go get 会把下载的第三方包直接放到$GOPATH/src 下边

* 另外由于GOPATH对其管理下的库不能区分版本, 都是指master最新代码, 就有了依赖包的管理问题. 比如多个项目需要依赖同一个库的不同版本时, 必须分开设置GOPATH, 开发的时候在不同的工程直接切换, 
徒增开发和实现的复杂度

__vendor__
* 在Go 1.5 中vendor作为试验推出，在Go 1.6中作为默认参数被启用

* 在项目的目录下增加一个 vendor 目录来存放外部的包(external packages), 在这种模式下, 会将第三方依赖的源码下载到本地, 不同项目下可以有自己不同的vendor, 所有项目依赖的共同版本的可以放到src/verdor下

* 但是这样做又引入了新的问题, 随着项目的依赖增多代码库会越来越大

__dep__
* GO1.9之后由社区组织合作开发的, 并且golang官方将其定义为 official experiment. 在相当长的一段时间里面作为标准成为事实上的官方包管理工具

* dep因为不支持semantic import versioning, 被go官方丢弃. 为此社区和russ cox大打出手(
https://zhuanlan.zhihu.com/p/41627929)

__go module__
* 官方的包管理工具, 18年Russ Cox又开发了vgo的实验项目, 最终标准化并进入Go 1.11称为go module的实现


### Go Module介绍
* Go Module是在 Go 的 1.11版本开始引入, 但是默认该选项是关闭的, 直到1.13版本将会默认开启. 所以在1.11和1.12必须手动开启Go Module支持: export GO111MODULE=on. As of Go 1.11, the go command enables the use of modules when the current directory or any parent directory has a go.mod, provided the directory is outside $GOPATH/src. (Inside $GOPATH/src, for compatibility, the go command still runs in the old GOPATH mode, even if a go.mod is found. ).  Starting in Go 1.13, module mode will be the default for all development.

* GOPATH作用的改变，When using modules, GOPATH is no longer used for resolving imports. However, it is still used to store downloaded source code (in GOPATH/pkg/mod) and compiled commands (in GOPATH/bin).

* go.mod  
  * A module is a collection of Go packages stored in a file tree with a go.mod file at its root.    
  * The go.mod file defines the module's module path, which is also the import path used for the root directory,   
  * and its dependency requirements, which are the other modules needed for a successful build.   
  * Each dependency requirement is written as a module path and a specific semantic version.
  * 如果模块没有依赖任然建议加入go.mod: This supports working outside of GOPATH, helps communicate to the ecosystem that you are opting in to modules, and in addition the module directive in your go.mod serves as a definitive declaration of the identity of your code
  * 语法
    module to define the module path;
    go to set the expected language version;
    require to require a particular module at a given version or later;
    exclude to exclude a particular module version from use; 
    replace to replace a module version with a different module version.

* go.sum
  * go.sum contains the expected cryptographic checksums of the content of specific module versions.
  * 如果下载的依赖和和go.sum中的不匹配就会报错
  * Note that go.sum is not a lock file, it provides enough information for reproducible builds


### Goproxy
* 官方代理: go env -w GOPROXY=proxy.golang.org,direct

* 国内代理: go env -w GOPROXY=https://goproxy.cn,direct


__示例代码__
```
    package hello

    import "rsc.io/quote"

    func Hello() string {
        return quote.Hello()
    }
```


### Creating a new module
* go mod init example.com/hello

* cat go.mod
```
    module example.com/hello

    go 1.13
```

The go.mod file only appears in the root of the module  

Packages in subdirectories have import paths consisting of the module path plus the path to the subdirectory.  Such as example.com/hello/world


### Adding a dependency

直接import需要的module, 比如：import "rsc.io/quote", 这样在编译的时候会自动去

When it encounters an import of a package not provided by any module in go.mod, the go command automatically looks up the module containing that package from GOPROXY and adds it to go.mod, using the latest version. (“Latest” is defined as the latest tagged stable (non-prerelease) version, or else the latest tagged prerelease version, or else the latest untagged version.)

同时会下载isc.io/quote的依赖包, 但是只有直接依赖的包会加入到go.mod文件

cat go.mod
```
    module example.com/hello

    go 1.12

    require rsc.io/quote v1.5.2
```

go list -m all 列出当前模块的名字及其所有依赖的模块, 包括直接依赖和间接依赖. In the go list output, the current module, also known as the main module, is always the first line, followed by dependencies sorted by module path.

go list -m all
```
    example.com/hello
    golang.org/x/text v0.0.0-20170915032832-14c0d48ead0c
    rsc.io/quote v1.5.2
    rsc.io/sampler v1.3.0
```
The golang.org/x/text version v0.0.0-20170915032832-14c0d48ead0c is an example of a pseudo-version, which is the go command's version syntax for a specific untagged commit.


### Upgrading dependencies

直接 go get, 比如:  go get golang.org/x/text

如果想更新或者回退到某个指定的版本, 可以使用后跟版本号, 比如: go get rsc.io/sampler@v1.3.1

Note the explicit @v1.3.1 in the go get argument. In general each argument passed to go get can take an explicit version; the default is @latest, which resolves to the latest version as defined earlier.


### Removing unused dependencies

go mod tidy


### go module 常用命令介绍

go mod init example.com/hello  创建一个新的module

go list -m all  查看所有的包依赖及对应的依赖版本

go list -m -version rsc.io/sampler  list the available tagged versions of that module:

go mod tidy 移除所有不需要的依赖


### 参考文档

https://github.com/golang/go/wiki/Modules

https://blog.golang.org/using-go-modules

https://www.gitdig.com/go-mod-enterprise-work-1
