/*
 * 用于测试tcp服务器的客户端
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <signal.h>

int sockfd = 0;
void sigint(int signum) {
    printf ("客户机：关闭套接字...\n");
    if (close (sockfd) == -1) {
        perror ("close");
    }
    exit(0);
}

int main (int argc, char* argv[]) {
    if (argc < 3) {
        fprintf (stderr, "用法：%s <服务器IP地址> <端口号>\n", argv[0]);
        return -1;
    }

    if(signal(SIGINT, sigint) == SIG_ERR) {
        perror("signal error");
        return -1;
    }

    printf ("客户机：创建网络数据流套接字...\n");
    sockfd = socket (AF_INET, SOCK_STREAM, 0);
    if (sockfd == -1) {
        perror ("socket");
        return -1;
    }

    printf ("客户机：准备地址并连接...\n");
    struct sockaddr_in addr;
    addr.sin_family = AF_INET;
    addr.sin_port = htons (atoi (argv[2]));
    addr.sin_addr.s_addr = inet_addr (argv[1]);

    if (connect (sockfd, (struct sockaddr*)&addr, sizeof (addr)) == -1) {
        perror ("connect");
        return -1;
    }

    printf ("客户机：发送请求并接收响应...\n");
    for (;;) {
        char buf[1024];
        int r = read(0, buf, sizeof(buf));
        buf[r - 1] = '\0'; //干掉回车

        if (send(sockfd, buf, (strlen(buf) + 1) * sizeof(buf[0]), 0) == -1) {
            perror ("send");
            return -1;
        }
        ssize_t rb = recv (sockfd, buf, sizeof (buf), 0);
        if (rb == -1) {
            perror ("recv");
            return -1;
        }

        if (rb == 0) {
            printf ("客户机：服务器已宕机！\n");
            break;
        }

        printf ("< %s\n", buf);
    }

    return 0;
}
