<?php

/**
 * jenkins-trigger-build-websocket-client
 * Created by: Erlang Parasu erlangparasu 2021
 */

use Ratchet\RFC6455\Messaging\Frame;

require __DIR__ . '/vendor/autoload.php';

function _configs()
{
    return (require __DIR__ . '/env.php');
}

function _event($evt)
{
    echo '_event_at: ' . date('Y-m-d H:i:s') . PHP_EOL;

    $configs = _configs();
    if (isset($configs[$evt])) {
        $config = $configs[$evt];

        $JENKINS_URL = $config['JENKINS_URL'];
        $JOB_NAME = $config['JOB_NAME'];
        $USER = $config['USER'];
        $TOKEN = $config['TOKEN'];
        $TOKEN_NAME = $config['TOKEN_NAME'];

        if ($config['enabled']) {
            exec('curl ' . $JENKINS_URL . '/job/' . $JOB_NAME . '/build \
            --user ' . $USER . ':' . $TOKEN . ' \
            --data token=' . $TOKEN_NAME . '', $output);

            print_r($output);
        }
    }
}

function _main()
{
    $base_config = require __DIR__ . '/conf.php';

    $loop = React\EventLoop\Loop::get();
    \Ratchet\Client\connect($base_config['ws_url'], [], [], $loop)->then(function ($conn) use ($loop) {
        echo 'connect_at: ' . date('Y-m-d H:i:s') . PHP_EOL;

        $conn->on('message', function ($msg) use ($conn) {
            echo 'message_at: ' . date('Y-m-d H:i:s') . PHP_EOL;
            echo "received: {$msg}\n";

            try {
                $object = json_decode($msg);
                if (isset($object)) {
                    if (isset($object->msg)) {
                        $json = $object->msg;
                        $jd = json_decode($json);
                        if (isset($jd->t)) {
                            _event($jd->t);
                        }
                    }
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
        });

        $loop->addPeriodicTimer(30, function () use ($conn) {
            echo 'periodic_at: ' . date('Y-m-d H:i:s') . PHP_EOL;
            $conn->send(new Frame('', true, Frame::OP_PING));
            // $conn->send('~');
        });

        $conn->send('hello-from-client');
    }, function ($e) {
        echo 'error_at: ' . date('Y-m-d H:i:s') . PHP_EOL;
        echo "could not connect: {$e->getMessage()}\n";
    });

    echo 'run_at: ' . date('Y-m-d H:i:s') . PHP_EOL;
    $loop->run();
}

_main();
