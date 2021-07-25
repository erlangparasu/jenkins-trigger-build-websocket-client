<?php

/**
 * jenkins-trigger-build-websocket-client
 * Created by: Erlang Parasu erlangparasu 2021
 */

use Ratchet\RFC6455\Messaging\Frame;
use Symfony\Component\HttpClient\HttpClient;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/env.php';
require __DIR__ . '/conf.php';

function _jenkins_configs()
{
    return _env();
}

function _event($evt)
{
    echo '_event_at: ' . date('Y-m-d H:i:s') . PHP_EOL;

    $configs = _jenkins_configs();
    if (isset($configs[$evt])) {
        $config = $configs[$evt];

        $JENKINS_URL = $config['JENKINS_URL'];
        $JOB_NAME = $config['JOB_NAME'];
        $USER = $config['USER'];
        $TOKEN = $config['TOKEN'];
        $TOKEN_NAME = $config['TOKEN_NAME'];

        if ($config['enabled']) {
            try {
                exec('curl ' . $JENKINS_URL . '/job/' . $JOB_NAME . '/build \
                --user ' . $USER . ':' . $TOKEN . ' \
                --data token=' . $TOKEN_NAME . '', $output);

                print_r($output);
                echo 'try exec done.';
            } catch (\Throwable $th) {
                echo 'ERR: catch: ' . $th->getMessage();
            }
        }
    }
}

function _send_ack(array $data)
{
    echo '_send_ack_at: ' . date('Y-m-d H:i:s') . PHP_EOL;

    $content = '';
    try {
        $client = HttpClient::create();
        $response = $client->request('POST', _conf()['http_ack_url'], [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $data,
        ]);

        $statusCode = $response->getStatusCode();
        // $statusCode = 200

        $contentType = $response->getHeaders()['content-type'][0];
        // $contentType = 'application/json'

        $content = $response->getContent();
        // $content = '{"id":521583, "name":"symfony-docs", ...}'

        // $content = $response->toArray();
        // // $content = ['id' => 521583, 'nam
    } catch (\Throwable $th) {
        echo 'ERR: catch: ' . $th->getMessage();
    }

    return $content;
}

function _main()
{
    $base_conf = _conf();

    $loop = React\EventLoop\Loop::get();
    \Ratchet\Client\connect($base_conf['ws_url'], [], [], $loop)->then(function ($conn) use ($loop) {
        echo 'connect_at: ' . date('Y-m-d H:i:s') . PHP_EOL;

        $conn->on('message', function ($message) use ($conn) {
            echo 'message_at: ' . date('Y-m-d H:i:s') . PHP_EOL;
            echo "received: {$message}\n";

            try {
                $object = json_decode($message);
                if (isset($object)) {
                    if (isset($object->msg)) {
                        $json_msg = $object->msg;
                        $msg = json_decode($json_msg);
                        if (isset($msg->id) && isset($msg->t)) {
                            _event($msg->t);
                            echo 'done_event_at: ' . date('Y-m-d H:i:s') . PHP_EOL;

                            _send_ack(['id' => $msg->id]);
                            echo 'done_send_ack_at: ' . date('Y-m-d H:i:s') . PHP_EOL;
                        }
                    }
                }
            } catch (\Throwable $th) {
                echo 'ERR: catch: ' . $th->getMessage();
            }
        });

        $loop->addPeriodicTimer(30, function () use ($conn) {
            echo 'periodic_at: ' . date('Y-m-d H:i:s') . PHP_EOL;
            $conn->send(new Frame('', true, Frame::OP_PING));
            // $conn->send('~');
        });

        $conn->send(json_encode([
            'data' => 'hello-from-' . _conf()['app_name'],
        ]));
    }, function ($e) {
        echo 'error_at: ' . date('Y-m-d H:i:s') . PHP_EOL;
        echo "could not connect: {$e->getMessage()}\n";
    });

    echo 'run_at: ' . date('Y-m-d H:i:s') . PHP_EOL;
    $loop->run();
}

_main();
