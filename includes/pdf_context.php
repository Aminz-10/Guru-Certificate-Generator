<?php
/**
 * This file is part of FPDI
 *
 * @package   FPDI
 * @copyright Copyright (c) 2017 Setasign - Jan Slabon (https://www.setasign.com)
 * @license   http://opensource.org/licenses/mit-license The MIT License
 * @version   1.6.2
 */

/**
 * Class pdf_context
 */
class pdf_context
{
    /**
     * Mode constant
     *
     * @var integer
     */
    const MODE_FILE = 0;

    /**
     * Mode constant
     *
     * @var integer
     */
    const MODE_STRING = 1;

    /**
     * The file resource or the string
     *
     * @var string|resource
     */
    public $file;

    /**
     * The buffer
     *
     * @var string
     */
    public $buffer;

    /**
     * The current offset in the buffer
     *
     * @var integer
     */
    public $offset;

    /**
     * The length of the buffer
     *
     * @var integer
     */
    public $length;

    /**
     * Stack
     *
     * @var array
     */
    public $stack = array();

    /**
     * Type of the "file"
     *
     * @var integer
     */
    public $_mode;

    /**
     * Constructor
     *
     * @param resource $f
     */
    public function __construct(&$f)
    {
        $this->file =& $f;
        if (is_string($f)) {
            $this->_mode = self::MODE_STRING;
        } else {
            $this->_mode = self::MODE_FILE;
        }
        $this->reset();
    }

    /**
     * Get the position in the file stream
     *
     * @return int
     */
    public function getPos()
    {
        if ($this->_mode == self::MODE_FILE) {
            return ftell($this->file) - $this->length + $this->offset;
        } else {
            return 0; // Not fully supported for string mode in this minimal impl
        }
    }

    /**
     * Reset the buffer
     *
     * @param null|int $pos
     * @param int $l
     */
    public function reset($pos = null, $l = 100)
    {
        if ($this->_mode == self::MODE_FILE) {
            if (!is_null($pos)) {
                fseek($this->file, $pos);
            }

            $this->buffer = $l > 0 ? fread($this->file, $l) : '';
            $this->length = strlen($this->buffer);
            $this->offset = 0;
            $this->stack = array();
        } else {
             // Handle string mode if needed (implied context)
             // simplified for file resource usage
        }
    }

    /**
     * Ensure that there is content in the buffer
     *
     * @return boolean
     */
    public function ensureContent()
    {
        if ($this->offset >= $this->length - 1) {
            return $this->increaseLength();
        } else {
            return true;
        }
    }

    /**
     * Increase the length of the buffer
     *
     * @param int $l
     * @return boolean
     */
    public function increaseLength($l = 100)
    {
        if ($this->_mode == self::MODE_FILE) {
            if (feof($this->file)) {
                return false;
            }

            $newBuffer = fread($this->file, $l);
            $this->buffer .= $newBuffer;
            $this->length += strlen($newBuffer);
            
            return true;
        }
        
        return false;
    }
}
