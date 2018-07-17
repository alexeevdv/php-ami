<?php

namespace alexeevdv\ami;
use Psr\Log\LoggerInterface;

/**
 * Class AMI
 * @package alexeevdv\ami
 */
class AMI
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * Event Handlers
     *
     * @access private
     * @var array
     */
    private $eventHandlers;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param string $config is the name of the config file to parse or a parent agi from which to read the config
     * @param array $optconfig is an array of configuration vars and vals, stuffed into $this->config['asmanager']
     */
    public function __construct($server, $port, $username, $secret, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->connection = new Connection($server, $port, $username, $secret, $logger);
    }

    public function __destruct()
    {
        $this->connection->disconnect();
    }

    /**
     * Send a request
     *
     * @param string $action
     * @param array $parameters
     * @return array of parameters
     */
    public function sendRequest($action, array $parameters = [])
    {
        $request = new AMIRequest($action, $parameters);
        $this->connection->write($request->__toString());
        return $this->waitResponse();
    }

    /**
     * Wait for a response
     *
     * If a request was just sent, this will return the response.
     * Otherwise, it will loop forever, handling events.
     *
     * @param boolean $allow_timeout if the socket times out, return an empty array
     * @return array of parameters, empty on timeout
     */
    protected function waitResponse($allow_timeout = false)
    {
        $timeout = false;
        do {
            $type = null;
            $parameters = [];
            $buffer = $this->connection->readLine();
            while ($buffer != '') {
                $a = strpos($buffer, ':');
                if ($a) {
                    if (!count($parameters)) {// first line in a response?
                        $type = strtolower(substr($buffer, 0, $a));
                        if (substr($buffer, $a + 2) == 'Follows') {
                            // A follows response means there is a miltiline field that follows.
                            $parameters['data'] = '';
                            $buff = $this->connection->readLine();
                            while (substr($buff, 0, 6) != '--END ') {
                                $parameters['data'] .= $buff;
                                $buff = $this->connection->readLine();
                            }
                        }
                    }
                    // store parameter in $parameters
                    $parameters[substr($buffer, 0, $a)] = substr($buffer, $a + 2);
                }
                $buffer = trim($this->connection->readLine());
            }
            // process response
            switch ($type) {
                case '': // timeout occured
                    $timeout = $allow_timeout;
                    break;
                case 'event':
                    $this->processEvent($parameters);
                    break;
                case 'response':
                    break;
                default:
                    $this->getLogger()->debug('Unhandled response packet from Manager: ' . print_r($parameters, true));
                    break;
            }
        } while ($type != 'response' && !$timeout);
        return $parameters;
    }
    /**
     * Connect to Asterisk
     *
     * @example examples/sip_show_peer.php Get information about a sip peer
     *
     * @param string $server
     * @param string $username
     * @param string $secret
     * @return boolean true on success
     */
    public function connect()
    {
        if (!$this->connection->connect()) {
            return false;
        }

        $respose = $this->sendRequest('login', [
            'Username' => $this->connection->getUsername(),
            'Secret' => $this->connection->getSecret(),
        ]);

        if ($respose['Response'] != 'Success') {
            $this->getLogger()->debug("Failed to login.");
            $this->connection->disconnect();
            return false;
        }
        return true;
    }

    /**
     * Disconnect
     *
     * @example examples/sip_show_peer.php Get information about a sip peer
     */
    public function disconnect()
    {
        $this->logoff();
        $this->connection->disconnect();
    }

    // *********************************************************************************************************
    // **                       COMMANDS                                                                      **
    // *********************************************************************************************************
    /**
     * Set Absolute Timeout
     *
     * Hangup a channel after a certain time.
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+AbsoluteTimeout
     * @param string $channel Channel name to hangup
     * @param integer $timeout Maximum duration of the call (sec)
     */
    public function absoluteTimeout($channel, $timeout)
    {
        return $this->sendRequest('AbsoluteTimeout', [
            'Channel' => $channel,
            'Timeout' => $timeout,
        ]);
    }

    /**
     * Change monitoring filename of a channel
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ChangeMonitor
     * @param string $channel the channel to record.
     * @param string $file the new name of the file created in the monitor spool directory.
     */
    public function changeMonitor($channel, $file)
    {
        return $this->sendRequest('ChangeMonitor', [
            'Channel' => $channel,
            'File' => $file,
        ]);
    }

    /**
     * Execute Command
     *
     * @example examples/sip_show_peer.php Get information about a sip peer
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Command
     * @link http://www.voip-info.org/wiki-Asterisk+CLI
     * @param string $command
     * @param string $actionid message matching variable
     */
    public function command($command, $actionid = null)
    {
        $parameters = ['Command' => $command];
        if ($actionid) {
            $parameters['ActionID'] = $actionid;
        }
        return $this->sendRequest('Command', $parameters);
    }

    /**
     * Enable/Disable sending of events to this manager
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Events
     * @param string $eventmask is either 'on', 'off', or 'system,call,log'
     */
    public function events($eventmask)
    {
        return $this->sendRequest('Events', ['EventMask' => $eventmask]);
    }

    /**
     * Check Extension Status
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ExtensionState
     * @param string $exten Extension to check state on
     * @param string $context Context for extension
     * @param string $actionid message matching variable
     */
    public function extensionState($exten, $context, $actionid = null)
    {
        $parameters = ['Exten' => $exten, 'Context' => $context];
        if ($actionid) {
            $parameters['ActionID'] = $actionid;
        }
        return $this->sendRequest('ExtensionState', $parameters);
    }

    /**
     * Gets a Channel Variable
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+GetVar
     * @link http://www.voip-info.org/wiki-Asterisk+variables
     * @param string $channel Channel to read variable from
     * @param string $variable
     * @param string $actionid message matching variable
     */
    public function getVar($channel, $variable, $actionid = null)
    {
        $parameters = ['Channel' => $channel, 'Variable' => $variable];
        if ($actionid) {
            $parameters['ActionID'] = $actionid;
        }
        return $this->sendRequest('GetVar', $parameters);
    }

    /**
     * Hangup Channel
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Hangup
     * @param string $channel The channel name to be hungup
     */
    public function hangup($channel)
    {
        return $this->sendRequest('Hangup', ['Channel' => $channel]);
    }

    /**
     * List IAX Peers
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+IAXpeers
     */
    public function IAXPeers()
    {
        return $this->sendRequest('IAXPeers');
    }

    /**
     * List available manager commands
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ListCommands
     * @param string $actionid message matching variable
     */
    public function listCommands($actionid = null)
    {
        if ($actionid) {
            return $this->sendRequest('ListCommands', ['ActionID' => $actionid]);
        } else {
            return $this->sendRequest('ListCommands');
        }
    }

    /**
     * Logoff Manager
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Logoff
     */
    public function logoff()
    {
        return $this->sendRequest('Logoff');
    }

    /**
     * Check Mailbox Message Count
     *
     * Returns number of new and old messages.
     *   Message: Mailbox Message Count
     *   Mailbox: <mailboxid>
     *   NewMessages: <count>
     *   OldMessages: <count>
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+MailboxCount
     * @param string $mailbox Full mailbox ID <mailbox>@<vm-context>
     * @param string $actionid message matching variable
     */
    public function mailboxCount($mailbox, $actionid = null)
    {
        $parameters = ['Mailbox' => $mailbox];
        if ($actionid) {
            $parameters['ActionID'] = $actionid;
        }
        return $this->sendRequest('MailboxCount', $parameters);
    }

    /**
     * Check Mailbox
     *
     * Returns number of messages.
     *   Message: Mailbox Status
     *   Mailbox: <mailboxid>
     *   Waiting: <count>
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+MailboxStatus
     * @param string $mailbox Full mailbox ID <mailbox>@<vm-context>
     * @param string $actionid message matching variable
     */
    public function mailboxStatus($mailbox, $actionid = null)
    {
        $parameters = ['Mailbox' => $mailbox];
        if ($actionid) {
            $parameters['ActionID'] = $actionid;
        }
        return $this->sendRequest('MailboxStatus', $parameters);
    }

    /**
     * Monitor a channel
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Monitor
     * @param string $channel
     * @param string $file
     * @param string $format
     * @param boolean $mix
     */
    public function monitor($channel, $file = null, $format = null, $mix = null)
    {
        $parameters = ['Channel' => $channel];
        if ($file) {
            $parameters['File'] = $file;
        }
        if ($format) {
            $parameters['Format'] = $format;
        }
        if (!is_null($file)) {
            $parameters['Mix'] = ($mix) ? 'true' : 'false';
        }
        return $this->sendRequest('Monitor', $parameters);
    }
    /**
     * Originate Call
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Originate
     * @param string $channel Channel name to call
     * @param string $exten Extension to use (requires 'Context' and 'Priority')
     * @param string $context Context to use (requires 'Exten' and 'Priority')
     * @param string $priority Priority to use (requires 'Exten' and 'Context')
     * @param string $application Application to use
     * @param string $data Data to use (requires 'Application')
     * @param integer $timeout How long to wait for call to be answered (in ms)
     * @param string $callerid Caller ID to be set on the outgoing channel
     * @param string $variable Channel variable to set (VAR1=value1|VAR2=value2)
     * @param string $account Account code
     * @param boolean $async true fast origination
     * @param string $actionid message matching variable
     */
    public function originate(
        $channel,
        $exten = null,
        $context = null,
        $priority = null,
        $application = null,
        $data = null,
        $timeout = null,
        $callerid = null,
        $variable = null,
        $account = null,
        $async = null,
        $actionid = null
    ) {
        $parameters = array_filter([
            'Channel' => $channel,
            'Exten' => $exten,
            'Context' => $context,
            'Priority' => $priority,
            'Application' => $application,
            'Data' => $data,
            'Timeout' => $timeout,
            'CallerID' => $callerid,
            'Variable' => $variable,
            'Account' => $account,
            'Async' => is_null($async) ? null : ($async ? 'true' : 'false'),
            'ActionID' => $actionid,
        ], function ($value) {
            return !is_null($value);
        });

        return $this->sendRequest('Originate', $parameters);
    }

    /**
     * List parked calls
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ParkedCalls
     * @param string $actionid message matching variable
     */
    public function parkedCalls($actionid = null)
    {
        if ($actionid) {
            return $this->sendRequest('ParkedCalls', ['ActionID' => $actionid]);
        } else {
            return $this->sendRequest('ParkedCalls');
        }
    }

    /**
     * Ping
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Ping
     */
    public function ping()
    {
        return $this->sendRequest('Ping');
    }

    /**
     * Queue Add
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+QueueAdd
     * @param string $queue
     * @param string $interface
     * @param integer $penalty
     */
    public function queueAdd($queue, $interface, $penalty = 0)
    {
        $parameters = ['Queue' => $queue, 'Interface' => $interface];
        if ($penalty) {
            $parameters['Penalty'] = $penalty;
        }
        return $this->sendRequest('QueueAdd', $parameters);
    }

    /**
     * Queue Remove
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+QueueRemove
     * @param string $queue
     * @param string $interface
     */
    public function queueRemove($queue, $interface)
    {
        return $this->sendRequest('QueueRemove', ['Queue' => $queue, 'Interface' => $interface]);
    }

    /**
     * Queues
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Queues
     */
    public function queues()
    {
        return $this->sendRequest('Queues');
    }

    /**
     * Queue Status
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+QueueStatus
     * @param string $actionid message matching variable
     */
    public function queueStatus($actionid = null)
    {
        if ($actionid) {
            return $this->sendRequest('QueueStatus', ['ActionID' => $actionid]);
        } else {
            return $this->sendRequest('QueueStatus');
        }
    }

    /**
     * Redirect
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Redirect
     * @param string $channel
     * @param string $extrachannel
     * @param string $exten
     * @param string $context
     * @param string $priority
     */
    public function redirect($channel, $extrachannel, $exten, $context, $priority)
    {
        return $this->sendRequest('Redirect', [
            'Channel' => $channel,
            'ExtraChannel' => $extrachannel,
            'Exten' => $exten,
            'Context' => $context,
            'Priority' => $priority,
        ]);
    }

    /**
     * Set the CDR UserField
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+SetCDRUserField
     * @param string $userfield
     * @param string $channel
     * @param string $append
     */
    public function setCDRUserField($userfield, $channel, $append = null)
    {
        $parameters = ['UserField' => $userfield, 'Channel' => $channel];
        if ($append) {
            $parameters['Append'] = $append;
        }
        return $this->sendRequest('SetCDRUserField', $parameters);
    }

    /**
     * Set Channel Variable
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+SetVar
     * @param string $channel Channel to set variable for
     * @param string $variable name
     * @param string $value
     */
    public function setVar($channel, $variable, $value)
    {
        return $this->sendRequest('SetVar', [
            'Channel' => $channel,
            'Variable' => $variable,
            'Value' => $value,
        ]);
    }

    /**
     * Channel Status
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Status
     * @param string $channel
     * @param string $actionid message matching variable
     */
    public function status($channel, $actionid = null)
    {
        $parameters = ['Channel' => $channel];
        if ($actionid) {
            $parameters['ActionID'] = $actionid;
        }
        return $this->sendRequest('Status', $parameters);
    }
    /**
     * Stop monitoring a channel
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+StopMonitor
     * @param string $channel
     */
    public function stopMonitor($channel)
    {
        return $this->sendRequest('StopMonitor', ['Channel' => $channel]);
    }

    /**
     * Dial over Zap channel while offhook
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapDialOffhook
     * @param string $zapchannel
     * @param string $number
     */
    public function zapDialOffhook($zapchannel, $number)
    {
        return $this->sendRequest('ZapDialOffhook', [
            'ZapChannel' => $zapchannel,
            'Number' => $number,
        ]);
    }

    /**
     * Toggle Zap channel Do Not Disturb status OFF
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapDNDoff
     * @param string $zapchannel
     */
    public function zapDNDoff($zapchannel)
    {
        return $this->sendRequest('ZapDNDoff', ['ZapChannel' => $zapchannel]);
    }

    /**
     * Toggle Zap channel Do Not Disturb status ON
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapDNDon
     * @param string $zapchannel
     */
    public function zapDNDon($zapchannel)
    {
        return $this->sendRequest('ZapDNDon', ['ZapChannel' => $zapchannel]);
    }

    /**
     * Hangup Zap Channel
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapHangup
     * @param string $zapchannel
     */
    public function zapHangup($zapchannel)
    {
        return $this->sendRequest('ZapHangup', ['ZapChannel' => $zapchannel]);
    }
    /**
     * Transfer Zap Channel
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapTransfer
     * @param string $zapchannel
     */
    public function zapTransfer($zapchannel)
    {
        return $this->sendRequest('ZapTransfer', ['ZapChannel' => $zapchannel]);
    }

    /**
     * Zap Show Channels
     *
     * @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapShowChannels
     * @param string $actionid message matching variable
     */
    public function zapShowChannels($actionid = null)
    {
        if ($actionid) {
            return $this->sendRequest('ZapShowChannels', ['ActionID' => $actionid]);
        } else {
            return $this->sendRequest('ZapShowChannels');
        }
    }
    // *********************************************************************************************************
    // **                       MISC                                                                          **
    // *********************************************************************************************************
    /**
     * Add event handler
     *
     * Known Events include ( http://www.voip-info.org/wiki-asterisk+manager+events )
     *  Link - Fired when two voice channels are linked together and voice data exchange commences.
     *  Unlink - Fired when a link between two voice channels is discontinued, for example, just before call completion.
     *  Newexten -
     *  Hangup -
     *  Newchannel -
     *  Newstate -
     *  Reload - Fired when the "RELOAD" console command is executed.
     *  Shutdown -
     *  ExtensionStatus -
     *  Rename -
     *  Newcallerid -
     *  Alarm -
     *  AlarmClear -
     *  Agentcallbacklogoff -
     *  Agentcallbacklogin -
     *  Agentlogoff -
     *  MeetmeJoin -
     *  MessageWaiting -
     *  join -
     *  leave -
     *  AgentCalled -
     *  ParkedCall - Fired after ParkedCalls
     *  Cdr -
     *  ParkedCallsComplete -
     *  QueueParams -
     *  QueueMember -
     *  QueueStatusEnd -
     *  Status -
     *  StatusComplete -
     *  ZapShowChannels - Fired after ZapShowChannels
     *  ZapShowChannelsComplete -
     *
     * @param string $event type or * for default handler
     * @param string $callback function
     * @return boolean sucess
     */
    public function addEventHandler($event, $callback)
    {
        $event = strtolower($event);
        if (isset($this->event_handlers[$event])) {
            $this->getLogger()->debug("$event handler is already defined, not over-writing.");
            return false;
        }
        $this->eventHandlers[$event] = $callback;
        return true;
    }

    /**
     * Process event
     *
     * @access private
     * @param array $parameters
     * @return mixed result of event handler or false if no handler was found
     */
    protected function processEvent($parameters)
    {
        $ret = false;
        $e = strtolower($parameters['Event']);
        $this->getLogger()->debug("Got event.. $e");
        $handler = '';
        if (isset($this->eventHandlers[$e])) {
            $handler = $this->eventHandlers[$e];
        } elseif (isset($this->eventHandlers['*'])) {
            $handler = $this->eventHandlers['*'];
        }
        if (function_exists($handler)) {
            $this->getLogger()->debug("Execute handler $handler");
            $ret = $handler($e, $parameters, $this->connection->getServer(), $this->connection->getPort());
        } else {
            $this->getLogger()->debug("No event handler for event '$e'");
        }
        return $ret;
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }
}
