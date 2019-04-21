/*
 * producer & consumer model
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <pthread.h>

#define MAX_STOCK 20        // 仓库容量
char g_storage[MAX_STOCK];  // 仓库
size_t g_stock = 0;         // 当前库存

pthread_mutex_t g_mtx = PTHREAD_MUTEX_INITIALIZER;  // 初始化互斥量

// 条件变量, 开始都是不满足
pthread_cond_t g_full = PTHREAD_COND_INITIALIZER;  // 满仓
pthread_cond_t g_empty = PTHREAD_COND_INITIALIZER; // 空仓

// 显示库存
void show(const char* who, const char* op, char prod) {
    printf("%s:", who);
    for(size_t i = 0; i < g_stock; i++) {
        printf("%c", g_storage[i]);
    }
    printf("%s%c\n", op, prod);
}

// 生产者线程
void* producer(void* arg) {
    const char* who =(const char*)arg;

    for(;;) {
        // 避免多个线程对同一个全局变量g_stock访问,用互斥量加锁
        pthread_mutex_lock(&g_mtx);

        if(g_stock >= MAX_STOCK) {
            // 显示绿色
            printf("\033[;;32m%s:满仓！\033[0m\n", who);

            // 等待g_full条件变量并解锁互斥量
            pthread_cond_wait(&g_full, &g_mtx);
        }

        char prod = 'A' + rand() % 26;
        show(who, "<-", prod);
        g_storage[g_stock++] = prod;

        // 释放g_empty条件变量, 从消费者中唤出一个线程
        pthread_cond_signal(&g_empty);

        // 解锁互斥量
        pthread_mutex_unlock(&g_mtx);

        usleep((rand() % 100) * 1000);
    }
    return NULL;
}

// 消费者线程
void* customer(void* arg) {
    const char* who =(const char*)arg;

    for(;;) {
        //加锁
        pthread_mutex_lock(&g_mtx);

        if(!g_stock) {
            printf("\033[;;31m%s:空仓！\033[0m\n",who);//显示红色
            pthread_cond_wait(&g_empty, &g_mtx);
        }

        char prod = g_storage[--g_stock];
        show(who, "->", prod);

        pthread_cond_signal(&g_full);
        //从生产者中唤出一个线程, 但是生产者不会马上执行因为他还要获得g_mtx
        //等下边这句话执行了，他才执行
        pthread_mutex_unlock(&g_mtx);

        usleep((rand() % 100) * 1000);
    }

    return NULL;
}

int main(void) {
    //用当前系统时间初始化随机种子
    srand(time(NULL));

    pthread_attr_t attr;
    int error = pthread_attr_init(&attr);
    if(error) {
        fprintf(stderr, "pthread_attr_init: %s\n", strerror(error));
        return -1;
    }

    if((error = pthread_attr_setdetachstate(&attr, PTHREAD_CREATE_DETACHED)) != 0) {
        fprintf(stderr, "pthread_attr_setdetachstate: %s\n", strerror(error));
        return -1;
    }

    pthread_t tid;
    if((error = pthread_create(&tid, &attr, producer, "生产者")) != 0) {
        fprintf(stderr, "pthread_create: %s\n", strerror(error));
        return -1;
    }

    if((error = pthread_create(&tid, &attr, customer, "消费者")) != 0) {
        fprintf(stderr, "pthread_create: %s\n", strerror(error));
        return -1;
    }

    getchar();
    pthread_cond_destroy(&g_full);
    pthread_cond_destroy(&g_empty);

    return 0;
}
