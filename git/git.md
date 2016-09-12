# Git基础知识及常用命令
## Git结构示意图
 ![Git结构示意图](./git/git.png "Git结构示意图")

## 本地新建项目提交到远程服务器
```
先在github上创建一个仓库命名为"blog"
echo "# Git基础知识及应用" >> README.md
git init                                                  [初始化仓库]
git config -local user.email itczliang@gmail.com　        [设置用户邮箱]
git config -local user.name itczl22                       [设置用户名]
git remote add origin https://github.com/itczl22/blog.git [设置当前仓库对应的远程仓库]
git add README.md                                         [添加文件到index]
git commit -m "first commit"                              [提交文件到本地仓库]
git push origin master                                    [推送文件到远程仓库]
git push origin dev_branch                                [自动在远程创建dev分支, 提merge request]
[git branch --set-upstream-to=origin/master               [git pull直接从远程master拉取代码, 代替了git pull origin master]]
[git config --local push.default simple                   [simple表示只push当前分支, matching表示push所有分支并合并]]
```
对于以上配置也可以直接修改配置文件project-path/.git/config
- 配置文件示例  
![Git配置文件示例](./git/config.png "Git配置文件示例")

## Git中的文件状态
```
untracked      [从未add过]
unmodified     [commit之后]
modified       [add后又被修改过]
staged         [已add]
绿色M表示已add, 红色M表示未add, 首次add后文件状态会是'new file'.
```

## Git中添加不跟踪文件
- 修改project-path/.gitignore
```
  *.a        [忽略所有.a结尾的]
  !lib.a     [但是除了lib.a]
  *.[oa]     [忽略所有.o或者.a结尾的]
  build/     [忽略build目录]
  doc/*.txt  [会忽略'doc/notes.txt'但不包括'doc/server/arch.txt']
```

## Git的基本命令
- 常用的配置命令
```
  git config --global --list(-l)                        [全局的, 主目录下的.gitconfig]
  git config --local  --list(-l)                        [局部的, 当前项目下的.git/config]
  git config --local user.name  itczl22                 [配置user.email]
  git config --local user.email itczliang@gmail.com     [配置user.email]
  git config --local http.sslverify false               [不需要进行安全验证]
  git config --local credential.helper store|cache      [密码管理, store表示存到本地，cache表示缓存]
  git config --unset --local remote.origin.url          [删除配置的远程仓库地址]
  git config --local push.default simple                [simple表示只push当前分支, matching表示push所有分支并合并]
  git config --get remote.origin.url                    [获取仓库远程地址]
  global表示全局配置, local表示当前仓库配置. 以上local可以换成global
```
 - 分支相关命令
```
  git branch                              [查看本地分支]
  git checkout [-b] branch-name           [切换分支, -b表示创建, 一般都是从master创建]
  git branch -d|-D  branch-name           [删除分支, -d选项只能删除已经被当前分支所合并过的分支, -D表示强制删除]
  git branch -a                           [显示远程仓库所有分支]
  git branch -v                           [显示本地所有分支，及该分支的commit信息]
  git branch --merged                     [查看哪些分支已被并入当前分支]
  git branch --no-merged                  [查看哪些分支没有被并入当前分支]
  git branch -m obranch nbranch           [重命名分支]
  git push origin remote-branch           [推送到远程分支, 如果远程分支不存在则创建]
  git push origin --delete remote-branch  [删除远程分支]
```
- diff相关命令
```
  git diff                      [changed but not yet staged, 之前add后又做修改但还未add)
  git diff --staged(--cached)   [staged that will go into next commit, 只比较add后的文件的不同]
  git diff HEAD                 [all differences, 合并上边的两个]
  git diff master               [compare branches, 和master的不同, 未merge之前的所有修改除了新增加的文件）
  git diff origin master        [和远程的不同]
  git diff origin master -- file[比较某个文件的不同]
  git diff commit1  commit2     [比较两个commit版本的不同]
```
- 回滚相关命令
```
  git reset --soft              [commit, 回退到某个版本,只回退了commit的信息,不会恢复到index file一>级]
  git reset --mixed             [commit & index, 默认方式, 只保留源码，回退commit和index信息]
  git reset --hard              [all source, 彻底回退到某个版本, 此命令慎用! 相当于代码没写, 但是不会回退pull]
  git reset --hard commit-id    [回滚到某个指定版本]
  git reset  HEAD test.cpp      [撤销对test.cpp的add, 等价于git reset -- test.cpp]
  git checkout -- test.cpp      [撤销对test.cpp的修改]
  git checkout commit-id        [回到某个版本]
  git revert commit commit-id   [撤销指定的commit]
```
- 常用的基本命令
```
  git init           [初始化仓库]
  git add            [stage文件]
  git commit         [提交文件]
  git clone          [从远程克隆项目]
  git status         [查看项目状态]
  git rm             [删除, 包括本地和远程]
  git rm --cached    [删除远程文件, 本地保留]
  git mv             [移动文件]
  git log            [查看提交历史]
  git checkout       [切换分支]
  git push           [推送到远程]
  git pull           [从远程更新]                          ~
```
- 其他命令
```
  git commit -am "something awesome"  [不用add直接提交，前提不是新文件]
  git commit --amend                  [重新commit，可以重新修改之前的commit信息]
  git remote show origin              [显示远程仓库信息]
  git grep [-n]                       [查找指定内容, -n表示显示行数]
  git log -2                          [显示最近两次提交信息]
  git log -p -2                       [-p shows the difference introduced in each commit]
  git log --reverse                   [倒叙显示]
  git tag                             [列出当前所有的标签]
  git tag -a pro-v1.4 -m 'version 1.4'[建立标签1.4]
  git tag -a v1.5 Zff5CwbQ            [当前分支Zff5CwbQ 作为一个标签1.5]
```
 
