/*
 * A simple tcp server using level triggered epoll model"
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <fcntl.h>
#include <errno.h>
#include <signal.h>
#include <unistd.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <sys/epoll.h>

#define LISTEN_QUEUE 10
#define MAXEVENTS 64

int main(int argc, char** argv) {
    if(argc < 3) {
        fprintf(stderr, "用法：%s <服务器IP地址> <端口号>\n", argv[0]);
        return -1;
    }

    int epfd;                         //epoll file descriptor
    int svrfd;                        //服务器描述符
    struct sockaddr_in svraddr;       //server ip and port
    struct sockaddr_in cliaddr;       //client ip and port
    struct epoll_event event;         //epoll_ctl用作输入参数来设置fd
    struct epoll_event* pevent;       //epoll_wait用作输出参数返回就绪的fd
    char buf[1024];                   //存放数据的缓存

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

    //4.创建epoll file descriptor
    if((epfd = epoll_create(MAXEVENTS)) == -1) {
        printf("4:%m\n");
        close(svrfd);
        exit(-1);
    }

    //5.注册svrfd
    event.data.fd = svrfd;
    event.events = EPOLLIN;
    r = epoll_ctl(epfd, EPOLL_CTL_ADD, svrfd, &event);
    if(r == -1) {
        printf("5:%m\n");
        close(svrfd);
        exit(-1);
    }

    //6.new buffer where events are returned
    pevent = calloc(MAXEVENTS, sizeof(event));
    if(!pevent) {
        printf("6:%m\n");
        close(svrfd);
        exit(-1);
    }

    while(1) {
        //7.循环等待就绪事件
        int sum = epoll_wait(epfd, pevent, MAXEVENTS, -1);
        if(sum == -1) {
            printf("7:%m\n");
            break;
        }

        //8.epoll_wait返回分两种情况处理
        for(int i = 0; i < sum; i++) {
            if ((pevent[i].events & EPOLLERR) || (pevent[i].events & EPOLLHUP) || (!(pevent[i].events & EPOLLIN))) {
                printf("8:event error\n");
                close(pevent[i].data.fd);
                continue;
            } else if (svrfd == pevent[i].data.fd) {
                //8.1.如果有客户连接,accept返回并注册
                //在这做while(1)循环，有可能是svrfd中不止有一个连接请求，可能同时来多个
                socklen_t addrlen = sizeof (cliaddr);
                int clifd = accept(svrfd, (struct sockaddr*)&cliaddr, &addrlen);
                if(clifd == -1) {
                    printf("8.1:%m\n");
                    continue;
                }

                event.data.fd = clifd;
                event.events = EPOLLIN;
                r = epoll_ctl (epfd, EPOLL_CTL_ADD, clifd, &event);
                if (r == -1) {
                    printf("8.1.1:%m\n");
                    close(clifd);
                    continue;
                }
                printf("有客户连接: %s:%hu\n", inet_ntoa(cliaddr.sin_addr), ntohs(cliaddr.sin_port));
            } else {
                //8.2.有客户发送数据,处理请求
                r = recv(pevent[i].data.fd, buf, sizeof(buf), 0);
                if(r == 0) {
                    printf("有客户退出\n");
                    close(pevent[i].data.fd);
                }
                if(r == -1) {
                    printf("8.2:网络故障\n");
                    close(pevent[i].data.fd);
                }
                if(r > 0) {
                    send(pevent[i].data.fd, buf, r, 0);
                }
            }
        }
    }

    //9.关闭描述符
    close(svrfd);
    return 0;
}

