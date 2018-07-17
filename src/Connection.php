<?php

namespace alexeevdv\ami;

use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class Connection
 * @package alexeevdv\ami
 */
class Connection
{
    /**
     * @var string
     */
    private $server;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Connection constructor.
     * @param string $server
     * @param int $port
     * @param string $username
     * @param string $secret
     */
    public function __construct($server, $port, $username, $secret, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->port = $port;
        $this->username = $username;
        $this->secret = $secret;
        $this->logger = $logger;
    }

    /**
     * @param string $data
     * @return int|null
     */
    public function write($data)
    {
        $written = fwrite($this->socket, $data);
        if (!$written) {
            return null;
        }
        return $written;
    }

    /**
     * @return bool
     */
    public function connect()
    {
        $errno = $errstr = null;
        try {
            $this->socket = fsockopen($this->server, $this->port, $errno, $errstr);
        } catch (Exception $e) {
            $this->socket = false;
        }
        if ($this->socket == false) {
            $this->getLogger()->debug("Unable to connect to manager {$this->server}:{$this->port} ($errno): $errstr");
            return false;
        }

        // read the header
        $header = $this->readLine();
        if ($header === null) {
            $this->getLogger()->debug('Asterisk Manager header not received.');
            return false;
        }
        return true;
    }

    public function disconnect()
    {
        fclose($this->socket);
    }

    /**
     * @return null|string
     */
    public function readLine()
    {
        $line = fgets($this->socket, 4096);
        if ($line === false) {
            return null;
        }
        return $line;
    }

    /**
     * @return string
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }
}
