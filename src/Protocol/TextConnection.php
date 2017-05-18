<?php
/**
 * Created by PhpStorm.
 * User: loconox
 * Date: 18/05/2017
 * Time: 11:38
 */

namespace Zikarsky\React\Gearman\Protocol;


use Evenement\EventEmitter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use React\Stream\Stream;
use Zikarsky\React\Gearman\Command\Exception as ProtocolException;
use BadMethodCallException;

class TextConnection extends EventEmitter
{
    /**
     * @var Stream
     */
    protected $stream;

    /**
     * @var bool
     */
    protected $closed = false;

    /**
     * @var LoggerInterface
     */
    protected $logger = null;

    /**
     * Creates the connection on top of the async stream and with the given
     * command-factory/specification
     *
     * @param Stream                  $stream
     */
    public function __construct(Stream $stream)
    {
        $this->stream = $stream;
        $this->logger = new NullLogger();

        // install event-listeners, end event is not of interest
        $this->stream->on('data', function () {
            return call_user_func_array([$this, 'handleData'], func_get_args());
        });
        $this->stream->on('error', function ($error) {
            throw new ProtocolException("Stream-Error: $error");
        });
        $this->stream->on('close', function () {
            $this->closed = true;
            $this->emit('close', [$this]);
        });
    }

    /**
     * Sets a protocol logger
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handles incoming data (in byte form) and emits commands when fully read
     *
     * @param string $data
     */
    protected function handleData($data)
    {
        $this->emit('response', [$data]);
    }

    /**
     * Sends a command over the stream
     *
     * @throws BadMethodCallException when the connection is closed
     */
    public function send($strCommand)
    {
        if ($this->isClosed()) {
            throw new BadMethodCallException("Connection is closed. Cannot send commands anymore");
        }

        $deferred = new Deferred();
        $this->logger->info("> $strCommand");
        $this->stream->write($strCommand."\n");
        $this->stream->getBuffer()->on('full-drain', function () use ($deferred) {
            $this->once('response', function($data) use ($deferred) {
                $deferred->resolve($data);
            });
        });

        return $deferred->promise();
    }


    /**
     * Returns the closed status of the connection
     *
     * @return boolean
     */
    public function isClosed()
    {
        return $this->closed;
    }

    /**
     * Closes the connection
     */
    public function close()
    {
        if (!$this->isClosed()) {
            $this->stream->close();
        }
    }
}