/*
 * message queue - consumer
 */
#include <stdio.h>
#include <unistd.h>
#include <errno.h>
#include <sys/msg.h>

int main(void) {
    // 获取IPC键值
    key_t key = ftok(".", 622);
    if(key == -1) {
        perror("ftok");
        return -1;
    }

    // 获取消息队列
    int msqid = msgget(key, 0);
    if(msqid == -1) {
        perror("msgget");
        return -1;
    }


    // 从消息队列接收消息
    for(;;) {
        struct {
            long mtype; 
            char mtext[1024];
        }	msgbuf = {622, ""};

        ssize_t msgsz = msgrcv(msqid, &msgbuf, sizeof(msgbuf.mtext) - sizeof(msgbuf.mtext[0]), msgbuf.mtype, MSG_NOERROR/* | IPC_NOWAIT*/);
        if(msgsz == -1) {
            if(errno == EIDRM) {
                printf("消息队列(0x%08x/%d)已销毁！\n", key, msqid);
                // EIDRM只是针对阻塞模式, 非阻塞直接参数无效报错
                // 因为阻塞模式是你先掉的msgrcv此时msqid还存在，所以不会直接报参数错误
                break;
            }
            else if(errno == ENOMSG) {
                printf("现在没有消息, 干点儿别的...\n");
                sleep(1);
            }
            else {
                perror("msgrcv");
                return -1;
            }
        }else {
            printf("%04d< %s\n", msgsz, msgbuf.mtext);
        }
    }

    return 0;
}
