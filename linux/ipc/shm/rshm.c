#include <stdio.h>
#include <sys/shm.h>

int main(void) {
    // 获取IPC键值
    key_t key = ftok(".", 622);
    if(key == -1) {
        perror("ftok");
        return -1;
    }

    // 获取共享内存
    int shmid = shmget(key, 0, 0);
    if(shmid == -1) {
        perror("shmget");
        return -1;
    }

    // 加载共享内存
    void* shmaddr = shmat(shmid, NULL, 0);
    if(shmaddr ==(void*)-1) {
        perror("shmat");
        return -1;
    }

    // 读取共享内存
    // 在这死循环读取
    printf("共享内存(0x%08x/%d)：%s\n", key, shmid,(char*)shmaddr);//用字符串方式打印

    // 卸载共享内存
    if(shmdt(shmaddr) == -1) {
        perror("shmdt");
        return -1;
    }

    return 0;
}
