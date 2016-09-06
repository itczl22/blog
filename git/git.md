# Git基础知识及应用
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
