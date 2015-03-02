<?php

namespace Kasseler\Component\GetText\Reader;

class StringReader {
    /**
     * @var int
     */
    protected $_pos;

    /**
     * @var string
     */
    protected $_str;

    /**
     * @var int
     */
    protected $error = 0;

    /**
     * @param string $str
     */
    public function __construct($str = '')
    {
        $this->_str = $str;
        $this->_pos = 0;
    }

    /**
     * @param $bytes
     *
     * @return string
     */
    public function read($bytes)
    {
        $data = substr($this->_str, $this->_pos, $bytes);
        $this->_pos += $bytes;
        if (strlen($this->_str) < $this->_pos){
            $this->_pos = strlen($this->_str);
        }

        return $data;
    }

    /**
     * @param $pos
     *
     * @return int
     */
    public function seekto($pos)
    {
        $this->_pos = $pos;
        if (strlen($this->_str) < $this->_pos){
            $this->_pos = strlen($this->_str);
        }

        return $this->_pos;
    }

    /**
     * @return mixed
     */
    public function currentPos()
    {
        return $this->_pos;
    }

    /**
     * @return int
     */
    public function length()
    {
        return strlen($this->_str);
    }

};
