# Git基础知识及常用命令
## Git结构示意图
![Git结构示意图](/git/git.png "Git结构示意图")

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
![Git配置文件示例](/git/config.png "Git配置文件示例")

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
- 常用的进阶命令
```
asdf
```
 
