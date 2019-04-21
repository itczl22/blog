/*
 * A simple demo for semaphore
 */
#include <stdio.h>
#include <string.h>
#include <unistd.h>
#include <pthread.h>
#include <semaphore.h>

#define MAX_CONNS 5  // 最大连接数
#define MAX_USERS 50 // 最大用户数

//定义信号量
sem_t g_sem;

void* thread_user(void* arg) {
    pthread_t tid = pthread_self();
    int sval = 0;

    //获取信号量的值
    sem_getvalue(&g_sem, &sval);
    printf("%lu线程:等待数据库连接(还剩%d个空闲连接)...\n", tid, sval);

    //等待信号量
    sem_wait(&g_sem);
    sem_getvalue(&g_sem, &sval);
    printf("%lu线程:获得数据库连接(还剩%d个空闲连接)...\n", tid, sval);

    usleep(1000000);

    //释放信号量
    sem_post(&g_sem);
    sem_getvalue(&g_sem, &sval);
    printf("%lu线程:释放数据库连接(还剩%d个空闲连接)...\n", tid, sval);

    return NULL;
}

int main(void) {
    pthread_t tids[MAX_USERS];
    int error;

    //初始化信号量
    sem_init(&g_sem, 0, MAX_CONNS);

    //创建线程
    for(size_t i = 0; i < sizeof(tids) / sizeof(tids[0]); i++) {
        if((error = pthread_create(&tids[i], NULL, thread_user, NULL)) != 0) {
            fprintf(stderr, "pthread_create: %s\n", strerror(error));
            return -1;
        }
    }

    //回收线程
    for(size_t i = 0; i < sizeof(tids) / sizeof(tids[0]); i++) {
        if((error = pthread_join(tids[i], NULL)) != 0) {
            fprintf(stderr, "pthread_join: %s\n", strerror(error));
            return -1;
        }
    }

    //销毁信号量
    sem_destroy(&g_sem);
    return 0;
}

