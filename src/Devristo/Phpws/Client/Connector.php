<?php

namespace Devristo\Phpws\Client;

use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;
use React\Promise\When;

class Connector implements \React\Socket\ConnectorInterface
{
    /**
     *
     * @var \React\Socket\Connector
     */
    private $connector;
    protected $contextOptions = array();

    public function __construct(LoopInterface $loop, Resolver $resolver, array $contextOptions = null)
    {
        $this->connector = new \React\Socket\Connector($loop, ['dns' => $resolver]);

	$contextOptions = null === $contextOptions ? array() : $contextOptions;
        $this->contextOptions = $contextOptions;
    }

    public function create($host, $port) {
	return $this->connector->connect(sprintf('%s:%s', $host, $port));
    }

    public function createSocketForAddress($address, $port, $hostName = null)
    {
        $url = $this->getSocketUrl($address, $port);

        $contextOpts = $this->contextOptions;
        // Fix for SSL in PHP >= 5.6, where peer name must be validated.
        if ($hostName !== null) {
            $contextOpts['ssl']['SNI_enabled'] = true;
            $contextOpts['ssl']['SNI_server_name'] = $hostName;
            $contextOpts['ssl']['peer_name'] = $hostName;
        }

        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $context = stream_context_create($contextOpts);
        $socket = stream_socket_client($url, $errno, $errstr, 0, $flags, $context);

        if (!$socket) {
            return When::reject(new \RuntimeException(
                sprintf("connection to %s:%d failed: %s", $address, $port, $errstr),
                $errno
            ));
        }

        stream_set_blocking($socket, 0);

        // wait for connection

        return $this
            ->waitForStreamOnce($socket)
            ->then(array($this, 'checkConnectedSocket'))
            ->then(array($this, 'handleConnectedSocket'));
    }

    public function connect($uri): \React\Promise\PromiseInterface {
	return $this->connector->connect($uri);
    }

}
