* client_example.go
```go
// The Client's Transport typically has internal state (cached TCP
// connections), so Clients should be reused instead of created as
// needed. Clients are safe for concurrent use by multiple goroutines.
//
// transport
tr := &http.Transport{
	Dial: (&net.Dialer{
		Timeout:   30 * time.MilliSecond,
		KeepAlive: 30 * time.MilliSecond,
	}).Dial,
	TLSHandshakeTimeout:   10 * time.MilliSecond,
	ResponseHeaderTimeout: 10 * time.MilliSecond,
	ExpectContinueTimeout: 1 * time.Second,  
    
	IdleConnTimeout:       1 * time.Second,     
	MaxIdleConnsPerHost:   100,      // 默认是2, 对于请求量均匀高并发的服务来说IdleConnTimeout内连接不会收不到请求, 所以空闲连接不会很多.
                                              // 但是对于请求量不均匀的服务, 如果最大空闲连接设置小了, 请求上涨时大量建立连接, 
                                              // 请求下降时大量关闭连接, 如此循环会造成大量的time_wait
    
	MaxConnsPerHost:       3000    // 单机最大连接数，如果服务端有10台机器就会有30000个最大连接. 没啥用啊？做负载均衡？
	MaxResponseHeaderBytes: 1 << 20,
}

// client
cli := &http.Client{
	Transport: tr,
	Timeout:   200 * time.Millisecond,
}

// request
req, err := http.NewRequest("POST", "https://itczl.com", strings.NewReader("name=itczl"))
if err != nil {
	// handle error
}
req.Header.Set("Cookie", "uid=12345")

// trace
// a facility to gather fine-grained information throughout the lifecycle of an HTTP client request
trace := &httptrace.ClientTrace{
    GotConn: func(connInfo httptrace.GotConnInfo) {
        fmt.Printf("Got Conn: %+v\n", connInfo)
    },
}
req = req.WithContext(httptrace.WithClientTrace(req.Context(), trace))
    
// do
resp, err := client.Do(req)
defer resp.Body.Close()  // 只有关闭了body, tcp连接才能被重用, 如果client拿到body还有大量逻辑要处理就没必要defer时关闭, 直接在读完body后就释放连接

// read resp
body, err := ioutil.ReadAll(resp.Body)
if err != nil {
	// handle error
}
```

* client.Do
```go
// Do sends an HTTP request and returns an HTTP response, following
// policy (such as redirects, cookies, auth) as configured on the client.
func (c *Client) Do(req *Request) (*Response, error) {
    return c.do(req)
}
```

* client.do
```go
func (c *Client) do(req *Request) (retres *Response, reterr error) {
    var (
        deadline      = c.deadline()    // 获取超时时间
        reqs          []*Request
        resp          *Response
        // ...
    )
    // 这个for是为了client主动发起重定向请求
    for {
        // 发起重定向请求
        if len(reqs) > 0 {
            loc := resp.Header.Get("Location")
            // ...    
            err = c.checkRedirect(req, reqs)
            // ...
        }
        // 处理正常请求【非server重定向的请求】
        reqs = append(reqs, req)
        if resp, didTimeout, err = c.send(req, deadline); err != nil {
            // c.send() always closes req.Body
            reqBodyClosed = true
            if !deadline.IsZero() && didTimeout() {    // 超时返回超时
                err = &httpError{
                    err:     err.Error() + " (Client.Timeout exceeded while awaiting headers)",
                    timeout: true,
                }
            }
            return nil, uerr(err)
        }
        // …
        req.closeBody()
    }
}
```

* client.send
```go
// didTimeout is non-nil only if err != nil.
func (c *Client) send(req *Request, deadline time.Time) (resp *Response, didTimeout func() bool, err error) {
    // ...
    resp, didTimeout, err = send(req, c.transport(), deadline)
    if err != nil {
        return nil, didTimeout, err
    }
    // ...
    return resp, nil, nil
}
```

* http.send
```go
// send issues an HTTP request.
// Caller should close resp.Body when done reading from it.
func send(ireq *Request, rt RoundTripper, deadline time.Time) (resp *Response, didTimeout func() bool, err error) {
	req := ireq // req is either the original request, or a modified fork
	// ...
	// 设置超时取消    
	stopTimer, didTimeout := setRequestCancel(req, rt, deadline)

	// 发送请求
	resp, err = rt.RoundTrip(req)
	if err != nil {
		stopTimer() //

		return nil, didTimeout, err
	}
	// ...
	return resp, nil, nil
}
```

* transport.RoundTrip
```go
// RoundTrip implements the RoundTripper interface.
//
// For higher-level HTTP client support (such as handling of cookies
// and redirects), see Get, Post, and the Client type.
//
// Like the RoundTripper interface, the error types returned
// by RoundTrip are unspecified.
func (t *Transport) RoundTrip(req *Request) (*Response, error) {
	return t.roundTrip(req)
}
```

* transport.roundTrip
```go
// roundTrip implements a RoundTripper over HTTP.
func (t *Transport) roundTrip(req *Request) (*Response, error) {
    // ...
    // 用来获取client trace, 具体参考: https://blog.golang.org/http-tracing
    trace := httptrace.ContextClientTrace(ctx)
    
    // for 循环是为了出现网络错误时进行重试
    // Transport only retries a request upon encountering a network error
    // if the request is idempotent and either has no body or has its Request.GetBody defined.    
    for {
        // ...
        // 获取一个持久连接
        // Get the cached or newly-created connection to either the
        // host (for http or https), the http proxy, or the http proxy
        // pre-CONNECTed to https server. In any case, we'll be ready
        // to send it requests.
        pconn, err := t.getConn(treq, cm)
        if err != nil {
            t.setReqCanceler(req, nil)
            req.closeBody()
            return nil, err
        }
        var resp *Response
        if pconn.alt != nil {
            // HTTP/2 path.
            t.decHostConnCount(cm.key()) // don't count cached http2 conns toward conns per host
            t.setReqCanceler(req, nil)   // not cancelable with CancelRequest
            resp, err = pconn.alt.RoundTrip(req)
        } else {
            // 执行persist connetion中的roundTrip获取response
            resp, err = pconn.roundTrip(treq)
        }
        if err == nil {
            return resp, nil
        }
        // ...
        // 出错是否自动重试
        if !pconn.shouldRetryRequest(req, err) {
            // ...
            return nil, err
        }
   
        // ...
    }
}
```

* transport.getConn
```go
// getConn dials and creates a new persistConn to the target as
// specified in the connectMethod. This includes doing a proxy CONNECT
// and/or setting up TLS.  If this doesn't return an error, the persistConn
// is ready to write requests to.
func (t *Transport) getConn(treq *transportRequest, cm connectMethod) (*persistConn, error) {
	// ...
	// 从空闲连接中获取一个
	if pc, idleSince := t.getIdleConn(cm); pc != nil {
		// ...
		return pc, nil
	}

	// 1.建立一个连接
	type dialRes struct {
		pc  *persistConn
		err error
	}
	dialc := make(chan dialRes)
	cmKey := cm.key()	
	go func() {
		pc, err := t.dialConn(ctx, cm)
		dialc <- dialRes{pc, err}
	}()

	// 2.获取一个空闲链接
	idleConnCh := t.getIdleConnCh(cm)
	// 1和2哪个先到就用哪个
	select {
	case v := <-dialc:
		// Our dial finished.
		if v.pc != nil {
			if trace != nil && trace.GotConn != nil && v.pc.alt == nil {
				trace.GotConn(httptrace.GotConnInfo{Conn: v.pc.conn})
			}
			return v.pc, nil
		}
		// ...
	case pc := <-idleConnCh:
		// Another request finished first and its net.Conn
		// became available before our dial. Or somebody
		// else's dial that they didn't use.
		// But our dial is still going, so give it away
		// when it finishes:
		handlePendingDial()
		if trace != nil && trace.GotConn != nil {
			trace.GotConn(httptrace.GotConnInfo{Conn: pc.conn, Reused: pc.isReused()})
		}
		return pc, nil
	// ...
	}
}
// 获取链接会优先从连接池中获取，如果连接池中没有可用的连接，则会创建一个连接或者从刚刚释放的连接中获取一个
// 这两个过程时同时进行的，谁先获取到连接就用谁的
```

* transport.dialConn
```go
func (t *Transport) dialConn(ctx context.Context, cm connectMethod) (*persistConn, error) {
	// ...
	pconn.br = bufio.NewReader(pconn)
	pconn.bw = bufio.NewWriter(persistConnWriter{pconn})

	// 起个协程循环读取server返回的数据
	// 读完之后, 如果alive==true则放回连接池
	go pconn.readLoop()
	
	// 起个协程循环写请求给server
	go pconn.writeLoop()

	return pconn, nil
}
```

客户端的超时控制
![客户端超时控制](./pic/http_client_timeout.png)
