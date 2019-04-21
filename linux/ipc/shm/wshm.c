/*
 * A simple shared memory programming demo
 * 该示例对多进程是不安全的, 可以借助信号量来实现同步机制
 */
#include <stdio.h>
#include <unistd.h>
#include <sys/shm.h>

int main(void) {
    // 获取IPC键值
    key_t key = ftok(".", 622);
    if(key == -1) {
        perror("ftok");
        return -1;
    }

    // 创建共享内存返回共享内存id
    // size建议取内存页字节数(4k=4096)的整数倍
    int shmid = shmget(key, 4096, 0644 | IPC_CREAT | IPC_EXCL);
    if(shmid == -1) {
        perror("shmget");
        return -1;
    }

    // 加载共享内存返回映射地址
    void* shmaddr = shmat(shmid, NULL, 0);
    if(shmaddr ==(void*)-1) {
        perror("shmat");
        return -1;
    }

    // 写入共享内存
    // 在这死循环写入
    sprintf(shmaddr, "我是%u进程写入的数据.", getpid());

    // 卸载共享内存
    if(shmdt(shmaddr) == -1) {
        perror("shmdt");
        return -1;
    }

    printf("按<回车>销毁共享内存(0x%08x/%d)...", key, shmid);
    getchar();
    if(shmctl(shmid, IPC_RMID, NULL) == -1) {
        perror("shmctl");
        return -1;
    }

    return 0;
}
