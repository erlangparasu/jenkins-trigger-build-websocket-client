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

function _logger($msg)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function _jenkins_configs()
{
    return _env();
}

function _event($evt)
{
    _logger('_event:');

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
                $final_cmd = 'curl ' . $JENKINS_URL . '/job/' . $JOB_NAME . '/build \
                --user ' . $USER . ':' . $TOKEN . ' \
                --data token=' . $TOKEN_NAME . '';

                var_dump($final_cmd);
                echo PHP_EOL;

                exec($final_cmd, $output);

                print_r($output);
                echo PHP_EOL;

                _logger('try exec done.');
            } catch (\Throwable $th) {
                _logger('ERR: catch: ' . $th->getMessage());
            }
        }
    }
}

function _send_ack(array $data)
{
    _logger('_send_ack:');

    $content = '';
    try {
        $client = HttpClient::create();
        $response = $client->request('POST', _conf()['http_ack_url'], [
            // 'headers' => [
            //     'Content-Type' => 'application/json',
            // ],

            'json' => $data,
        ]);
        // echo 'AAAA' . PHP_EOL;

        $statusCode = $response->getStatusCode();
        // $statusCode = 200
        // echo 'BBBB' . $statusCode . PHP_EOL;

        $contentType = $response->getHeaders(false)['content-type'][0];
        // $contentType = 'application/json'
        // echo 'CCCC' . $contentType . PHP_EOL;

        $content = $response->getContent(false);
        // $content = '{"id":521583, "name":"symfony-docs", ...}'
        // echo 'DDDD' . $content . PHP_EOL;

        // $content = $response->toArray();
        // // $content = ['id' => 521583, 'nam
    } catch (\Throwable $th) {
        _logger('ERR: catch: ' . $th->getMessage());
    }

    return $content;
}

function _main()
{
    _logger('_main:');

    $base_conf = _conf();

    $loop = React\EventLoop\Loop::get();
    \Ratchet\Client\connect($base_conf['ws_url'], [], [], $loop)->then(function ($conn) use ($loop) {
        _logger('on connect:');

        $conn->on('close', function ($code, $reason, $ws) use ($loop) {
            _logger('on close:');
            $loop->stop();
        });

        $conn->on('ping', function ($frame, $ws) {
            _logger('on ping:');
        });

        $conn->on('pong', function ($frame, $ws) {
            _logger('on pong:');
        });

        $conn->on('error', function ($error, $ws) use ($loop) {
            _logger('on error:');
            $loop->stop();
        });

        $conn->on('message', function ($message, $self) use ($conn) {
            _logger('on message:');
            _logger("received: {$message}\n");

            try {
                $object = json_decode($message);
                if (isset($object)) {
                    if (isset($object->msg)) {
                        $json_msg = $object->msg;

                        $msg = json_decode($json_msg);
                        if (isset($msg->id) && isset($msg->data) && isset($msg->data->header_token)) {
                            _event($msg->data->header_token);
                            _logger('_event done');

                            $response = _send_ack(['id' => $msg->id]);
                            _logger('_send_ack done');
                            _logger('  response: --->' . $response . '<---');
                        }
                    }
                }
            } catch (\Throwable $th) {
                _logger('ERR: catch: ' . $th->getMessage());
            }
        });

        $loop->addPeriodicTimer(30, function () use ($conn) {
            _logger('on periodic:');
            $conn->send(new Frame('', true, Frame::OP_PING));
            // $conn->send('~');
        });

        $conn->send(json_encode([
            'data' => 'hello-from-' . _conf()['app_name'],
        ]));
        _logger('conn sent:');
    }, function ($e) use ($loop) {
        _logger('conn error:');
        _logger("could not connect: {$e->getMessage()}\n");
        $loop->stop();
    });

    _logger('_main done');
}

_main();

_logger('exit:');
exit(0);
