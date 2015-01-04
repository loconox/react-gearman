<?php

use Zikarsky\React\Gearman\WorkerInterface;
use Zikarsky\React\Gearman\Event\TaskDataEvent;
use Zikarsky\React\Gearman\JobInterface;
use Zikarsky\React\Gearman\Factory;

require_once __DIR__ . "/../../vendor/autoload.php";

// use default options
$factory = new Factory();

$factory->createWorker("127.0.0.1", 4730)->then(
    // on successful creation
    function (WorkerInterface $worker) {
        $worker->setId('Test-Client/' . getmypid());
        $worker->register('ping', function(JobInterface $job) {
            $result = [];
            foreach ($job->getWorkload() as $host) {
                echo "ping: $host\n"; 
                $result[$host] = `ping -c 2 -q $host | grep rtt`;
                $job->sendData($result);
            }

            return $result;
        });
    },
    // error-handler
    function($error) {
        echo "Error: $error\n";
    }
);

$factory->getEventLoop()->run();
