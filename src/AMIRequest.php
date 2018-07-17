<?php

namespace alexeevdv\ami;

/**
 * Class AMIRequest
 * @package alexeevdv\ami
 */
class AMIRequest
{
    /**
     * @var string
     */
    private $action;

    /**
     * @var array
     */
    private $params;

    /**
     * AMIRequest constructor.
     * @param string $action
     * @param array $params
     */
    public function __construct($action, array $params = [])
    {
        $this->action = $action;
        $this->params = $params;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $string = "Action: {$this->action}\r\n";
        foreach ($this->params as $key => $value) {
            $string .= "$key: $value\r\n";
        }
        $string .= "\r\n";
        return $string;
    }
}
