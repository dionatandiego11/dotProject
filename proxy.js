const http = require('http');
const net = require('net');

const LOCAL_PORT = 8080;
const REMOTE_HOST = '172.22.19.35';
const REMOTE_PORT = 80;

const server = http.createServer((clientReq, clientRes) => {
    const options = {
        hostname: REMOTE_HOST,
        port: REMOTE_PORT,
        path: clientReq.url,
        method: clientReq.method,
        headers: clientReq.headers
    };

    const proxyReq = http.request(options, (proxyRes) => {
        clientRes.writeHead(proxyRes.statusCode, proxyRes.headers);
        proxyRes.pipe(clientRes, {
            end: true
        });
    });

    proxyReq.on('error', (e) => {
        console.error(`Problem with request: ${e.message}`);
        clientRes.writeHead(502);
        clientRes.end('Proxy Error: ' + e.message);
    });

    clientReq.pipe(proxyReq, {
        end: true
    });
});

server.listen(LOCAL_PORT, () => {
    console.log(`Proxy running on http://localhost:${LOCAL_PORT} -> ${REMOTE_HOST}:${REMOTE_PORT}`);
});
