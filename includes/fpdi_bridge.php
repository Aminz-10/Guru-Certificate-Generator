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
 * Class fpdi_bridge
 *
 * This class maps specific calls to the used PDF-generation library
 */
class fpdi_bridge extends FPDF
{
    /**
     * Array of arrays (array(0 => $n, 1 => $value))
     *
     * @var array
     */
    protected $_objStack = array();

    /**
     * Done object stack
     *
     * @var array
     */
    protected $_doneObjStack = array();

    /**
     * Encryption class (TCPDF)
     *
     * @var object
     */
    protected $_enc;

    /**
     * Current Object Id.
     *
     * @var integer
     */
    public $currentObjId;

    /**
     * Method to convert a string to hex.
     *
     * @param string $str
     * @return string
     */
    public function hex2str($str)
    {
        return pack('H*', str_replace(array("\r", "\n", ' '), '', $str));
    }

    /**
     * Method to convert a string to hex.
     *
     * @param string $str
     * @return string
     */
    public function str2hex($str)
    {
        return current(unpack('H*', $str));
    }
}
