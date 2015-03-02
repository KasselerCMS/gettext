<?php

namespace Kasseler\Component\GetText\Reader;

class FileReader {
    /**
     * @var int
     */
    protected $_pos;

    /**
     * @var mixed
     */
    protected $_fd;

    /**
     * @var string
     */
    protected $_length;

    /**
     * @var int
     */
    public $error = null;

    /**
     * @param $filename
     */
    public function __construct($filename)
    {
        if (file_exists($filename)) {
            $this->_length = filesize($filename);
            $this->_pos = 0;
            $this->_fd = fopen($filename, 'rb');
            $this->error = $this->_fd ?: 3; // Cannot read file, probably permissions

        } else {
            $this->error = 2; // File doesn't exist
        }

        return $this->error > 0 ? false : true;
    }

    /**
     * @param $bytes
     *
     * @return string
     */
    public function read($bytes)
    {
        if (!$bytes) {
            return '';
        }
        fseek($this->_fd, $this->_pos);
        $data = '';

        while ($bytes > 0) {
            $chunk  = fread($this->_fd, $bytes);
            $data .= $chunk;
            $bytes -= strlen($chunk);
        }
        $this->_pos = ftell($this->_fd);

        return $data;
    }

    /**
     * @param $pos
     *
     * @return int
     */
    public function seekto($pos)
    {
        fseek($this->_fd, $pos);
        $this->_pos = ftell($this->_fd);

        return $this->_pos;
    }

    /**
     * @return int
     */
    public function currentPos()
    {
        return $this->_pos;
    }

    /**
     * @return string
     */
    public function length()
    {
        return $this->_length;
    }

    /**
     * @return bool
     */
    public function close()
    {
        return fclose($this->_fd);
    }

};
