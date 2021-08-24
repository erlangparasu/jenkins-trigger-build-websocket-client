<?php

/**
 * Created by: Erlang Parasu erlangparasu 2021
 */

$server = new \Swoole\Websocket\Server('127.0.0.1', 8800);

$server->on('open', function ($server, $req) {
    echo "connection open: {$req->fd}\n";
});

$server->on('message', function ($server, $frame) {
    echo "received message: {$frame->data}\n";
    foreach ($server->connections as $fd) {
        $server->push($fd, json_encode(['msg' => $frame->data]));
    }
});

$server->on('close', function ($server, $fd) {
    echo "connection close: {$fd}\n";
});

echo 'server-is-starting' . PHP_EOL;
$server->start();

exit(0);
