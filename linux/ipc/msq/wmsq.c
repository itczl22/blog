/*
 * message queue - producer
 */
#include <stdio.h>
#include <string.h>
#include <sys/msg.h>

int main(void) {
    // 获取IPC键值
    key_t key = ftok(".", 622);
    if(key == -1) {
        perror("ftok");
        return -1;
    }

    // 创建消息队列
    int msqid = msgget(key, 0644 | IPC_CREAT | IPC_EXCL);
    if(msqid == -1) {
        perror("msqget");
        return -1;
    }


    // 向消息队列发送数据
    for(;;) {
        struct {
            long mtype;
            char mtext[1024];
        } msgbuf = {622, ""};

        printf("> ");
        gets(msgbuf.mtext);
        if(!strcmp(msgbuf.mtext, "end")) {
            break;
        }

        if(msgsnd(msqid, &msgbuf, strlen(msgbuf.mtext) * sizeof(msgbuf.mtext[0]), 0) == -1) {
            perror("msgsnd");
            return -1;
        }
    }

    // 销毁消息队列
    if(msgctl(msqid, IPC_RMID, NULL) == -1) {
        perror("msgctl");
        return -1;
    }

    return 0;
}
