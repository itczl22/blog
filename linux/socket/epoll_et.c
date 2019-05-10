/*
 * A simple tcp server using edge triggered epoll model"
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <errno.h>
#include <fcntl.h>
#include <signal.h>
#include <unistd.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <sys/epoll.h>

#define LISTEN_QUEUE 10
#define MAXEVENTS 64

int non_blocking(int fd) {
    int flags = fcntl(fd, F_GETFL, 0);
    if (flags == -1) {
        perror ("fcntl");
        return -1;
    }

    flags |= O_NONBLOCK;
    flags = fcntl(fd, F_SETFL, flags);
    if (flags == -1) {
        perror ("fcntl");
        return -1;
    }
    return 0;
}

int main(int argc, char** argv) {
    if (argc < 3) {
        fprintf (stderr, "用法：%s <服务器IP地址> <端口号>\n", argv[0]);
        return -1;
    }

    int epfd;                         //epoll file descriptor
    int svrfd;                        //服务器描述符
    struct sockaddr_in svraddr;       //server ip and port
    struct sockaddr_in cliaddr;       //client ip and port
    struct epoll_event event;         //epoll_ctl用作输入参数来设置fd
    struct epoll_event* pevent;       //epoll_wait用作输出参数返回就绪的fd
    char buf[1024];                   //存放数据的缓存
    int numfds = 0;                   //被监听描述符的个数

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

    //3.设置socket为非阻塞
    r = non_blocking(svrfd);
    if(r == -1) {
        printf("3:non_blocking errror\n");
        close(svrfd);
        exit(-1);
    }

    //4.监听
    r = listen(svrfd, LISTEN_QUEUE);
    if(r == -1) {
        printf("4:%m\n");
        close(svrfd);
        exit(-1);
    }

    //5.创建epoll file descriptor
    if((epfd = epoll_create(MAXEVENTS)) == -1) {
        printf("5:%m\n");
        close(svrfd);
        exit(-1);
    }

    //6.注册svrfd
    event.data.fd = svrfd;
    event.events = EPOLLIN | EPOLLET;
    r = epoll_ctl (epfd, EPOLL_CTL_ADD, svrfd, &event);
    if (r == -1) {
        printf("6:%m\n");
        close(svrfd);
        exit(-1);
    }

    /* Buffer where events are returned */
    pevent = calloc(MAXEVENTS, sizeof(event));

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
                while(1) {
                    socklen_t addrlen = sizeof (cliaddr);
                    int clifd = accept(svrfd, (struct sockaddr*)&cliaddr, &addrlen);
                    if(clifd == -1) {
                        if(errno == EAGAIN) {
                            break;
                        }
                        printf("8.1:%m\n");
                        break;
                    }

                    r = non_blocking(clifd);
                    if (r == -1) {
                        printf("8.1.1:non_blocking errror\n");
                        close(clifd);
                        break;
                    }

                    event.data.fd = clifd;
                    event.events = EPOLLIN | EPOLLET;
                    r = epoll_ctl(epfd, EPOLL_CTL_ADD, clifd, &event);
                    if (r == -1) {
                        printf("8.1.2:%m\n");
                        close(clifd);
                        break;
                    }
                    printf("有客户连接: %s:%hu\n", inet_ntoa(cliaddr.sin_addr), ntohs(cliaddr.sin_port));
                }
            } else {
                //8.2.有客户发送数据,处理请求
                //边缘触发必须读完，否则数据会丢失，因为他不会通知第二次
                while(1) {
                    r = recv(pevent[i].data.fd, buf, sizeof(buf), 0);
                    if(r == 0) {
                        printf("有客户退出\n");
                        close(pevent[i].data.fd);
                        if(epoll_ctl(epfd, EPOLL_CTL_DEL, pevent[i].data.fd, NULL) == -1 ) {
                            printf("8.1.3:%m\n");
                            break;
                        }
                        break;
                    }
                    if(r == -1) {
                        if(errno == EAGAIN) {
                            break;
                        }
                        printf("8.2:网络故障\n");
                        close(pevent[i].data.fd);
                        if(epoll_ctl(epfd, EPOLL_CTL_DEL, pevent[i].data.fd, NULL) == -1 ) {
                            printf("8.2.1:%m\n");
                            break;
                        }
                        break;
                    }
                    if(r > 0) {
                        if(send(pevent[i].data.fd, buf, r, 0) == -1) {
                            close(pevent[i].data.fd);
                            if(epoll_ctl(epfd, EPOLL_CTL_DEL, pevent[i].data.fd, NULL) == -1 ) {
                                printf("8.2.2:%m\n");
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    printf("服务器退出...\n");
    close(svrfd);
    return 0;
}

