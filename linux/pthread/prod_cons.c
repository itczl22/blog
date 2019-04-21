/*
 * A simple model of producer & consumer
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <pthread.h>

#define MAX_STOCK 20         // 仓库容量

char g_storage[MAX_STOCK];   // 仓库
size_t g_stock = 0;          // 当前库存数据个数

pthread_mutex_t g_mtx = PTHREAD_MUTEX_INITIALIZER; // 保护仓库独享操作
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

// 生产者线程过程函数
void* producer(void* arg) {
    const char* who =(const char*)arg;

    for(;;) {
        // 加锁
        pthread_mutex_lock(&g_mtx);

        // 判断条件
        // 注: 对于单个生产者可以用if, 对于多个生产者必须用while
        //     因为当他拿到互斥量时必须再次判断条件,以防被其他生产者修改
        while(g_stock >= MAX_STOCK) {
            printf("\033[;;32m%s:满仓！\033[0m\n", who);
            pthread_cond_wait(&g_full, &g_mtx);
        }

        // 生产
        char prod = 'A' + rand() % 26;
        show(who, "<-", prod);
        g_storage[g_stock++] = prod;

        // 唤醒全部消费者
        pthread_cond_broadcast(&g_empty);

        // 解锁互斥量
        pthread_mutex_unlock(&g_mtx);

        usleep((rand() % 100) * 1000);
    }
    return NULL;
}

// 消费者线程过程函数, 流程和生产者线程过程函数一样
void* customer(void* arg) {
    const char* who =(const char*)arg;

    for(;;) {
        pthread_mutex_lock(&g_mtx);

        while(! g_stock) {
            printf("\033[;;31m%s:空仓！\033[0m\n",who);//显示红色
            pthread_cond_wait(&g_empty, &g_mtx);
        }

        char prod = g_storage[--g_stock];
        show(who, "->", prod);
        pthread_cond_broadcast(&g_full);

        pthread_mutex_unlock(&g_mtx);

        usleep((rand() % 100) * 1000);
    }
    return NULL;
}

int main(void) {
    // 用当前系统时间初始化随机种子
    srand(time(NULL));

    // 初始化线程属性
    pthread_attr_t attr;
    int error = pthread_attr_init(&attr);
    if(error) {
        fprintf(stderr, "pthread_attr_init: %s\n", strerror(error));
        return -1;
    }

    // 设置线程分离
    if((error = pthread_attr_setdetachstate(&attr, PTHREAD_CREATE_DETACHED)) != 0) {
        fprintf(stderr, "pthread_attr_setdetachstate: %s\n", strerror(error));
        return -1;
    }

    // 创建线程
    pthread_t tid;
    if((error = pthread_create(&tid, &attr, producer, "生产者1")) != 0) {
        fprintf(stderr, "pthread_create: %s\n", strerror(error));
        return -1;
    }

    if((error = pthread_create(&tid, &attr, producer, "生产者2")) != 0) {
        fprintf(stderr, "pthread_create: %s\n", strerror(error));
        return -1;
    }

    if((error = pthread_create(&tid, &attr, customer, "消费者1")) != 0) {
        fprintf(stderr, "pthread_create: %s\n", strerror(error));
        return -1;
    }

    if((error = pthread_create(&tid, &attr, customer, "消费者2")) != 0) {
        fprintf(stderr, "pthread_create: %s\n", strerror(error));
        return -1;
    }

    getchar();
    return 0;
}
