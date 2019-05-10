/*
 * A simple tcp server using select model
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <fcntl.h>
#include <signal.h>
#include <unistd.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <sys/select.h>

#define LISTEN_QUEUE 10

int svrfd;                   //服务端fd
int maxfd;                   //记录最大的描述符
fd_set active_fds;           //客户端fds

//服务退出，关闭描述符
void sigint(int signum) {
    printf ("服务器退出...\n");
    close(svrfd);
    for(int i = 0; i < maxfd; i++ ) {
        if(FD_ISSET(i, &active_fds)) {
            close(i);
        }
    }
    exit(0);
}

int main(int argc, char** argv) {
    if (argc < 3) {
        fprintf (stderr, "用法：%s <服务器IP地址> <端口号>\n", argv[0]);
        return -1;
    }

    struct sockaddr_in svraddr;  //IP和端口
    struct sockaddr_in cliaddr;  //IP和端口
    fd_set select_fds;           //被select监控的描述符集合
    char buf[1024];              //存放数据的缓存

    //绑定信号，处理服务器退出
    if(signal(SIGINT, sigint) == SIG_ERR) {
        perror("signal");
        return -1;
    }

    //1.建立socket
    svrfd = socket(AF_INET, SOCK_STREAM, 6);
    if(svrfd == -1) {
        printf("1:%m\n");
        exit(-1);
    }

    //2.绑定地址与端口
    svraddr.sin_family = AF_INET;
    svraddr.sin_port = htons(atoi(argv[2]));
    inet_aton(argv[1], &svraddr.sin_addr);
    int r = bind(svrfd, (struct sockaddr*)&svraddr, sizeof(svraddr));
    if(r == -1) {
        printf("2:%m\n");
        close(svrfd);
        exit(-1);
    }

    //3.监听
    r = listen(svrfd, LISTEN_QUEUE);
    if(r == -1) {
        printf("3:%m\n");
        close(svrfd);
        exit(-1);
    }

    //4.初始化
    maxfd = 0;
    FD_ZERO(&active_fds);
    FD_ZERO(&select_fds);

    //5.加入服务器fd
    FD_SET(svrfd, &active_fds);
    maxfd = maxfd >= svrfd ? maxfd : svrfd;

    while(1) {
        //6.重置监听描述符集合
        select_fds = active_fds;

        //7.使用select循环控制描述符号集合, 阻塞等待消息，当select返回时，无消息的fd会从select_fds里清除
        r = select(maxfd+1, &select_fds, 0, 0, 0);
        if(r == -1) {
            printf("服务器崩溃\n");
            break;
        }

        //8.select返回分两种情况处理
        //8.1.如果有客户连接:服务器描述符号集合
        if(FD_ISSET(svrfd, &select_fds)) {
            socklen_t addrlen = sizeof (cliaddr);
            int cli = accept(svrfd, (struct sockaddr*)&cliaddr, &addrlen);
            if(cli == -1) {
                printf("服务器崩溃\n");
                break;
            }
            printf("有客户连接: %s:%hu\n", inet_ntoa(cliaddr.sin_addr), ntohs(cliaddr.sin_port));
            
            FD_SET(cli, &active_fds); // 客户端连接的fd加入到active_fds里边
            maxfd = maxfd >= cli ? maxfd : cli;
        }

        //8.2.有客户发送数据:客户代理描述符集合
        for(int i = 0; i <= maxfd; i++) {
            if(i != svrfd && FD_ISSET(i, &select_fds)) {
                r = recv(i, buf, sizeof(buf), 0);
                if(r == 0) {
                    printf("有客户退出\n");
                    close(i);
                    FD_CLR(i, &active_fds);
                }
                if(r == -1) {
                    perror("网络故障\n");
                    close(i);
                    FD_CLR(i, &active_fds);
                }
                if(r > 0) {
                    send(i, buf, r, 0);
                }
            }
        }
    }
    return 0;
}
