/*
 * A simple demo for mutex
 */
#include <stdio.h>
#include <string.h>
#include <pthread.h>

//定义互斥量,必须是全局的
pthread_mutex_t g_mtx;
unsigned int g_cn = 0;

void* thread_add(void* arg) {
    for(size_t i = 0; i < 10000000; i++) {
        pthread_mutex_lock(&g_mtx);
        ++g_cn;
        pthread_mutex_unlock(&g_mtx);
    }
    return NULL;
}

void* thread_sub(void* arg) {
    for(size_t i = 0;i < 10000000;++i) {
        pthread_mutex_lock(&g_mtx);
        --g_cn;
        pthread_mutex_unlock(&g_mtx);
    }
    return NULL;
}

int main(void) {
    pthread_t tids[2];
    int error;

    //初始化互斥量
    pthread_mutex_init(&g_mtx, NULL);

    //创建2个线程
    for(size_t i = 0; i < sizeof(tids) / sizeof(tids[0]); i++) {
        if((error = pthread_create(&tids[i], NULL, i?thread_sub:thread_add, NULL)) != 0) {
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

    //释放互斥量
    pthread_mutex_destroy(&g_mtx);

    printf("g_cn = %u\n", g_cn);
    return 0;
}

