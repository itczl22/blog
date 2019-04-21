/*
 * A simple tcp server using poll model"
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
#include <poll.h>

#define POLL_SIZE 32
#define LISTEN_QUEUE 10

struct pollfd poll_set[POLL_SIZE];  // 被监听的文件描述符集合
int numfds = 0;                     // 被监听描述符的个数

// 服务退出，关闭描述符
void sigint(int signum) {
    printf ("服务器退出...\n");
    for(int index = 0; index < numfds; index++) {
        close(poll_set[index].fd);
        poll_set[index].events = 0;
    }
    exit(0);
}

int main(int argc, char** argv) {
    if(argc < 3) {
        fprintf (stderr, "用法：%s <服务器IP地址> <端口号>\n", argv[0]);
        return -1;
    }

    int svrfd;                        // 服务器描述符
    struct sockaddr_in svraddr;       // IP和端口
    struct sockaddr_in cliaddr;       // IP和端口
    char buf[1024];                   // 存放数据的缓存

    // 绑定信号，处理服务器退出
    if(signal(SIGINT, sigint) == SIG_ERR) {
        perror("signal");
        return -1;
    }

    // 1.建立socket
    svrfd = socket(AF_INET, SOCK_STREAM, 6);
    if(svrfd == -1) {
        printf("1:%m\n");
        exit(-1);
    }

    // 2.绑定地址与端口
    svraddr.sin_family = AF_INET;
    svraddr.sin_port = htons(atoi(argv[2]));
    inet_aton(argv[1], &svraddr.sin_addr);
    int r = bind(svrfd, (struct sockaddr*)&svraddr, sizeof(svraddr));
    if(r == -1) {
        printf("2:%m\n");
        close(svrfd);
        exit(-1);
    }

    // 3.监听
    r = listen(svrfd, LISTEN_QUEUE);
    if(r == -1) {
        printf("3:%m\n");
        close(svrfd);
        exit(-1);
    }

    // 4.加入服务器描述符
    poll_set[numfds].fd = svrfd;
    poll_set[numfds].events = POLLIN;
    numfds++;

    while(1) {
        // 5.使用poll循环控制描述符集合
        r = poll(poll_set, numfds, -1);
        if(r == -1) {
            printf("5:%m\n");
            break;
        }

        // 6.poll返回分两种情况处理
        for(int index = 0; index < numfds; index++) {
            // 6.1.如果有客户连接,accept返回并加入到监控
            if(poll_set[index].revents & POLLIN ) {
                if(poll_set[index].fd == svrfd) {
                    socklen_t addrlen = sizeof (cliaddr);
                    int cli = accept(svrfd, (struct sockaddr*)&cliaddr, &addrlen);
                    if(cli == -1) {
                        printf("6.1:%m\n");
                        continue;
                    }
                    printf("有客户连接: %s:%hu\n", inet_ntoa(cliaddr.sin_addr), ntohs(cliaddr.sin_port));
                    poll_set[numfds].fd = cli;
                    poll_set[numfds].events = POLLIN;
                    numfds++;
                } else {
                    // 6.2.有客户发送数据,处理请求
                    r = recv(poll_set[index].fd, buf, sizeof(buf), 0);
                    if(r == 0) {
                        printf("有客户退出\n");
                        close(poll_set[index].fd);
                        poll_set[index].events = 0;
                        for(int i = index; i < numfds; i++) {
                            poll_set[i] = poll_set[i + 1];
                        }
                        numfds--;
                    }
                    if(r == -1) {
                        printf("6.2:%m\n");
                        close(poll_set[index].fd);
                        poll_set[index].events = 0;
                        for(int i = index; i < numfds; i++) {
                            poll_set[i] = poll_set[i + 1];
                        }
                        numfds--;
                    }
                    if(r > 0) {
                        send(poll_set[index].fd, buf, r, 0);
                    }
                }
            }
        }
    }
    return 0;
}

