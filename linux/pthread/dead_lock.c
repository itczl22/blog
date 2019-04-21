/*
 * A simple demo for dead lock
 * 死锁只能人为避免,系统无法解决
 */
#include <stdio.h>
#include <string.h>
#include <unistd.h>
#include <pthread.h>

//初始化两个互斥量
pthread_mutex_t g_mtxa = PTHREAD_MUTEX_INITIALIZER;
pthread_mutex_t g_mtxb = PTHREAD_MUTEX_INITIALIZER;

//线程过程函数1, 获取A资源等待B资源
void* thread_proc1 (void* arg) {
    pthread_t tid = pthread_self ();

    printf ("%lu线程：等待A...\n", tid);
    pthread_mutex_lock (&g_mtxa);
    printf ("%lu线程：获得A！\n", tid);

    sleep (1);

    printf ("%lu线程：等待B...\n", tid);
    pthread_mutex_lock (&g_mtxb);
    printf ("%lu线程：获得B！\n", tid);

    pthread_mutex_unlock (&g_mtxb);
    printf ("%lu线程：释放B。\n", tid);

    pthread_mutex_unlock (&g_mtxa);
    printf ("%lu线程：释放A。\n", tid);

    return NULL;
}

//线程过程函数2, 获取B资源等待A资源
void* thread_proc2 (void* arg) {
    pthread_t tid = pthread_self ();

    printf ("%lu线程：等待B...\n", tid);
    pthread_mutex_lock (&g_mtxb);
    printf ("%lu线程：获得B！\n", tid);

    sleep (1);

    printf ("%lu线程：等待A...\n", tid);
    pthread_mutex_lock (&g_mtxa);
    printf ("%lu线程：获得A！\n", tid);

    pthread_mutex_unlock (&g_mtxa);
    printf ("%lu线程：释放A。\n", tid);

    pthread_mutex_unlock (&g_mtxb);
    printf ("%lu线程：释放B。\n", tid);

    return NULL;
}

int main (void) {
    pthread_t tid1;
    int error = 0;
    if ((error = pthread_create (&tid1, NULL, thread_proc1, NULL)) != 0) {
        fprintf (stderr, "pthread_create: %s\n", strerror (error));
        return -1;
    }
    pthread_t tid2;
    if ((error = pthread_create (&tid2, NULL, thread_proc2, NULL)) != 0) {
        fprintf (stderr, "pthread_create: %s\n", strerror (error));
        return -1;
    }

    if ((error = pthread_join (tid1, NULL)) != 0) {
        fprintf (stderr, "pthread_join: %s\n", strerror (error));
        return -1;
    }
    if ((error = pthread_join (tid2, NULL)) != 0) {
        fprintf (stderr, "pthread_join: %s\n", strerror (error));
        return -1;
    }

    printf ("成功获取资源!!!\n");
    return 0;
}

