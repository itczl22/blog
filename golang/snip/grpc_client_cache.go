package transport

import (
	"math/rand"
	"sync"
	"time"

	"google.golang.org/grpc"
)

var (
	// grpcCache 连接池管理器
	grpcCache grpcClientCache
	// maxConnsNum 单host最大连接数
	maxConnsNum int = 10
)

// grpcClientCache host->conns 的映射
type grpcClientCache struct {
	cache sync.Map
}

// grpcClientPool 单个host的连接池
type grpcClientPool struct {
	address string
	maxNum  int
	rd      *rand.Rand
	conns   []*grpc.ClientConn
	sync.RWMutex
}

// GetGRPCClient 从指定host的连接池中获取一个连接
func GetGRPCClient(address string) (*grpc.ClientConn, error) {
	if v, ok := grpcCache.cache.Load(address); ok {
		if clientPool, ok := v.(*grpcClientPool); ok {
			return clientPool.getConn()
		}
	}

	// 每个host只走这一次
	clientPool := createGRPCClientPool(address)
	grpcCache.cache.Store(address, clientPool)

	return clientPool.getConn()
}

// createGRPCClientPool 创建某个host的连接池
func createGRPCClientPool(address string) *grpcClientPool {
	return &grpcClientPool{
		address: address,
		maxNum:  maxConnsNum,
		rd:      rand.New(rand.NewSource(time.Now().Unix())),
	}
}

// getConn 获取指定host的一个连接
func (this *grpcClientPool) getConn() (*grpc.ClientConn, error) {
	this.RLock()
	if len(this.conns) == this.maxNum {
		defer this.RUnlock()
		clientIdx := this.rd.Int() % this.maxNum
		return this.conns[clientIdx], nil
	}
	this.RUnlock()

	this.Lock()
	defer this.Unlock()
	// 如果同时有两个协程执行上边的枷锁操作,A成功,B等待
	// 当A执行完毕后，B拿到锁继续执行, 此时A协程已经创建了一个连接
	// B协程拿到锁后应该先判断是否有足够的链接, 有就使用没有再去创建
	if len(this.conns) == this.maxNum {
		clientIdx := this.rd.Int() % this.maxNum
		return this.conns[clientIdx], nil
	}

	client, err := createGRPCClient(this.address)
	if err != nil {
		return nil, err
	}
	this.conns = append(this.conns, client)

	return client, nil
}

// createGRPCClient 创建一个到指定host的连接
func createGRPCClient(address string) (*grpc.ClientConn, error) {
	return grpc.Dial(address, grpc.WithInsecure())
}
