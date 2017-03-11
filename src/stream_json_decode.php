=<?php
class stream_json_decode
{
    //
    // file stream
    // 
    private $stream = null;

    //
    // pop $stream
    //
    private $popStream = null; 

    //
    // collect match list
    // 
    private $matchList = array();

    //
    // delete target
    //
    private $delTarget = null;
    
    //
    // default callback row
    //
    public $row = 10;

    //
    // callback function name
    //
    public $callback = null;

    //
    // set false to decode utf8  
    //
    public $utf8 = true;

    //
    // set true to open debug mode
    //
    public $debug = false;

    /**
     *  Returns TRUE on success, otherwise false
     */
    public function setFile($fileName)
    {
        $isSet = true;
        $this->stream = fopen($fileName, 'rb');
        if (!$this->stream)
        {
            $isSet = false;
        }
        return $isSet;
    }

    /**
     *  start decode stream
     */
    public function json_decode()
    {
        //
        // assert()
        // - two parameters support started from 5.4.8
        if (!empty($callback))
        {
            if (version_compare(PHP_VERSION, '5.4.8') >= 0)
            {
                assert(is_callable($this->callback), __METHOD__.": not a valid callback.");
            } else
            {
                assert(is_callable($this->callback));
            }  // end if()
        }

        $data = array();
        if (!empty($this->stream))
        {
            while (!self::checkStream()) {
                $readChar = self::getStream();

                self::printMsg(__FUNCTION__.":".$readChar."\n");

                if ($readChar == '[')
                {
                    self::addMatch(']', 'square');
                    $data = self::findBracket();
                    if (self::delMatch($readChar, 'square'))
                    {
                        break;
                    } // end if()
                } // end if()
            } // end while ()
            return $data;
        } // end if()
    }

    private function findBracket()
    {
        $data = array();
        $row = 0;
        while (!self::checkStream()) {
            $readChar = self::getStream();
            
            self::printMsg(__FUNCTION__.":".$readChar."\n");

            if (self::checkMatch($readChar))
            {
                //last run
                if (empty($data) && is_array($data))
                {
                    if (is_array($this->callback))
                    {
                        call_user_func_array($this->callback, array($data));
                        $data = array();

                    } else {
                        $this->callback($data);
                        $data = array();
                    }  // end if()
                } // end if()

                return $data;
            }
            else if (!preg_match('/[\s\{,]+/', $readChar))
            {
                self::throwException("opps invalid json string {\n");
            }
            else if ($readChar == '{')
            {
                $data[$row] = array();
                self::addMatch('}', 'curly1');
                while (true)
                { 
                    $rKeyValue = self::getKeyValue();
                    if (!empty($rKeyValue))
                    {
                        $data[$row] = array_merge($data[$row], $rKeyValue);
                    } // end if()

                    if (self::delMatch($this->delTarget, 'curly1'))
                    {
                        break;
                    } // end if()
                }

                if ((($row + 1) % $this->row) == 0)
                {
                    //each row callback
                    if (is_array($this->callback))
                    {
                        call_user_func_array($this->callback, array($data));
                        $data = array();

                    } else {
                        $this->callback($data);
                        $data = array();
                    }  // end if()
                } // end if()

                $row++;
            }
        }
    }

    private function getKeyValue()
    {
        $data = array();
        while(true)
        {
            $rKey = self::findKey();
            
            if (!$rKey)
            {
                self::printMsg('line '.__LINE__." no key find\n");

                return $data;              
            }

            self::printMsg(__FUNCTION__.": GET KEY ".$rKey."\n");

            //UNESCAPED_UNICODE
            if (!$this->utf8 && is_string($rKey))
            {
                $rKey = self::decodeUnicodeString($rKey);
                $rKey = stripslashes($rKey);
            }
            $data[$rKey] = array();
            $checkColon = self::findColon();
            if(!$checkColon)
            {
                self::throwException(__FUNCTION__.":opps invalid json string :");
            } // end if()
            $rVal = self::findValue();

            if (is_string($rVal))
            {
                self::printMsg(__FUNCTION__.": GET VALUE ".$rVal."\n");
            }

            //UNESCAPED_UNICODE
            if (!$this->utf8 && is_string($rVal))
            {
                $rVal = self::decodeUnicodeString($rVal);
                $rVal = stripslashes($rVal);
            } // end if()

            $data[$rKey] = $rVal;
            
            return $data;
        }

        self::throwException(__FUNCTION__.":opps invalid json string");
    }

    private function findKey()
    {
        $str = '';
        $startKey = false;
        while (!self::checkStream()) {
            $readChar = self::getStream();
            
            self::printMsg(__FUNCTION__.":".$readChar."\n");
      
            if (self::delMatch($readChar, 'key'))
            {
                return $str;
            }
            else if (preg_match('/[\"]+/', $readChar))
            {
                self::addMatch('"', 'key');
                $str = '';
                $startKey = true;
            }
            else if (preg_match('/[\s]+/', $readChar))
            {
                continue;
            }
            else if ($startKey)
            {
                $str .= $readChar;
            }
            else{
                $this->delTarget = $readChar;
                return false;
            } // end if()
        }

        self::throwException(__FUNCTION__.":opps invalid json string");
    }

    private function findColon()
    {
        while (!self::checkStream()) {
            $readChar = self::getStream();
            
            self::printMsg(__FUNCTION__.":".$readChar."\n");
            
            if (!preg_match('/[\s:]+/', $readChar))
            {
                self::throwException(__FUNCTION__.":opps invalid json string :");
            }
            else
            {
                if ($readChar == ':')
                {
                    return true;
                }
            } // end if()
        }

        self::throwException(__FUNCTION__.":opps invalid json string");
    } 

    private function findValue()
    {
        $data = array();
        $str = '';
        $prev = end($this->matchList);
        $startValue = false;
        $startString = false;
        $startArray = false;
        $arrayData = array();
        $isDigit = false;
        $prevChar = '';
        $prevPrevChar = '';

        while (!self::checkStream()) {
            $readChar = self::getStream();

            self::printMsg(__FUNCTION__.":".$readChar."\n");

            if (preg_match('/[{]+/', $readChar) && !$startValue)
            {
                self::addMatch('}', 'curly2');
                while (true)
                { 
                    $rKeyValue = self::getKeyValue();
                    if (!empty($rKeyValue))
                    {
                        $data = array_merge($data, $rKeyValue);
                    }
                    if (self::delMatch($this->delTarget, 'curly2'))
                    {
                        break;
                    } // end if ()
                } // end while ()

                return $data;
            }
            else if (!$startValue && !$isDigit && (preg_match('/\[/', $readChar) || $startArray))
            {
                $startArray = true;                
                $str .= $readChar;
                if (preg_match('/\]/', $readChar))
                {
                    //todo handle by charread
                    $arrayData = json_decode($str);
                    if (json_last_error() != '')
                    {
                        $arrayData = array();
                    } // end if()

                    $startArray = false;
                    return $arrayData;
                } // end if()
            }
            else
            {
                if (self::delMatch($readChar, 'value'))
                {
                    //escape
                    if ($prevChar == '\\' && $prevPrevChar != '\\')
                    {
                        self::addMatch($readChar, 'value');
                        $str .= $readChar;
                    }
                    else
                    {
                        return trim($str);
                    } // end if()
                }
                else if (preg_match('/[\"]+/', $readChar) && !$isDigit)
                {
                    self::addMatch('"', 'value');
                    $str = '';
                    $startValue = true;
                }
                else if (is_numeric($readChar) && !$startValue)
                {
                    $str .= $readChar;
                    $isDigit = true;
                }
                else if (preg_match('/[,}]+/', $readChar) 
                    && $isDigit 
                    && !$startValue)
                {
                    if ($readChar == '}')
                    {
                        $this->delTarget = '}';
                    }
                    return (int)trim($str);
                }
                else if ($startValue)
                {
                    $str .= $readChar;
                }
                else if (preg_match('/[\[\]\s]+/', $readChar) && !$isDigit)
                {
                    continue;
                }
                else if (!$isDigit) {
                    if (preg_match('/n/', $readChar))
                    {
                        //
                        // null
                        //                        
                        $str .= $readChar;
                        $readChar = self::getStream();
                        $str .= $readChar;
                        $readChar = self::getStream();
                        $str .= $readChar;
                        $readChar = self::getStream();
                        $str .= $readChar;
                        if ($str == 'null')
                        {
                            return null;
                        }

                        self::throwException(__LINE__.":opps invalid json string ");
                    }
                    else if (preg_match('/t/', $readChar))
                    {
                        //
                        // true
                        //                        
                        $str .= $readChar;
                        $readChar = self::getStream();
                        $str .= $readChar;
                        $readChar = self::getStream();
                        $str .= $readChar;
                        $readChar = self::getStream();
                        $str .= $readChar;
                        if ($str == 'true')
                        {
                            return true;
                        }

                        self::throwException(__LINE__.":opps invalid json string ");
                    }
                    else if (preg_match('/f/', $readChar))
                    {
                        //
                        // false
                        //                        
                        $str .= $readChar;
                        $readChar = self::getStream();
                        $str .= $readChar;
                        $readChar = self::getStream();
                        $str .= $readChar;
                        $readChar = self::getStream();
                        $str .= $readChar;
                        if ($str == 'false')
                        {
                            return false;
                        }

                        self::throwException(__LINE__.":opps invalid json string ");
                    } 
                    else
                    {
                        return false;
                    }
                }
            }

            $prevPrevChar = $prevChar;
            $prevChar = $readChar;
        }

        self::throwException(__FUNCTION__.":opps invalid json string");
    }

    public function delMatch($symbol, $flag)
    {
        $check = false;
        $lastMatch = end($this->matchList);
        if ($lastMatch[0] == $symbol 
            && $lastMatch[1] == $flag)
        {
            self::printMsg(" delete \"".$lastMatch[0]."\" delete \"".$lastMatch[1]."\"\n");

            array_pop($this->matchList);
            $check = true;
            $this->delTarget = null;
        }
        return $check;
    }

    public function checkMatch($symbol)
    {
        $check = false;
        $lastMatch = end($this->matchList);
        if ($lastMatch[0] == $symbol)
        {
            $check = true;
        }
        return $check;
    }

    public function addMatch($symbol, $flag)
    {
        $check = false;
        if (!empty($symbol))
        {
            $this->matchList[] = array($symbol, $flag);
            $check = true;
        }
        return $check;
    }

    /**
     * Decode Unicode Characters from \u0000 ASCII syntax.
     *
     * This algorithm was originally developed for the
     * Solar Framework by Paul M. Jones
     *
     * @link   http://solarphp.com/
     * @link   http://svn.solarphp.com/core/trunk/Solar/Json.php
     * @param  string $value
     * @return string
     */
    public function decodeUnicodeString($chrs)
    {
        $delim       = substr($chrs, 0, 1);
        $utf8        = '';
        $strlen_chrs = strlen($chrs);

        for($i = 0; $i < $strlen_chrs; $i++) {

            $substr_chrs_c_2 = substr($chrs, $i, 2);
            $ord_chrs_c = ord($chrs[$i]);

            switch (true) {
                case preg_match('/\\\u[0-9A-F]{4}/i', substr($chrs, $i, 6)):
                    // single, escaped unicode character
                    $utf16 = chr(hexdec(substr($chrs, ($i + 2), 2)))
                           . chr(hexdec(substr($chrs, ($i + 4), 2)));
                    $utf8 .= self::_utf162utf8($utf16);
                    $i += 5;
                    break;
                case ($ord_chrs_c >= 0x20) && ($ord_chrs_c <= 0x7F):
                    $utf8 .= $chrs{$i};
                    break;
                case ($ord_chrs_c & 0xE0) == 0xC0:
                    // characters U-00000080 - U-000007FF, mask 110XXXXX
                    //see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                    $utf8 .= substr($chrs, $i, 2);
                    ++$i;
                    break;
                case ($ord_chrs_c & 0xF0) == 0xE0:
                    // characters U-00000800 - U-0000FFFF, mask 1110XXXX
                    // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                    $utf8 .= substr($chrs, $i, 3);
                    $i += 2;
                    break;
                case ($ord_chrs_c & 0xF8) == 0xF0:
                    // characters U-00010000 - U-001FFFFF, mask 11110XXX
                    // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                    $utf8 .= substr($chrs, $i, 4);
                    $i += 3;
                    break;
                case ($ord_chrs_c & 0xFC) == 0xF8:
                    // characters U-00200000 - U-03FFFFFF, mask 111110XX
                    // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                    $utf8 .= substr($chrs, $i, 5);
                    $i += 4;
                    break;
                case ($ord_chrs_c & 0xFE) == 0xFC:
                    // characters U-04000000 - U-7FFFFFFF, mask 1111110X
                    // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                    $utf8 .= substr($chrs, $i, 6);
                    $i += 5;
                    break;
            }
        }

        return $utf8;
    }

    /**
     * Convert a string from one UTF-16 char to one UTF-8 char.
     *
     * Normally should be handled by mb_convert_encoding, but
     * provides a slower PHP-only method for installations
     * that lack the multibye string extension.
     *
     * This method is from the Solar Framework by Paul M. Jones
     *
     * @link   http://solarphp.com
     * @param  string $utf16 UTF-16 character
     * @return string UTF-8 character
     */
    protected function _utf162utf8($utf16)
    {
        // Check for mb extension otherwise do by hand.
        if( function_exists('mb_convert_encoding') ) {
            return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
        }

        $bytes = (ord($utf16{0}) << 8) | ord($utf16{1});

        switch (true) {
            case ((0x7F & $bytes) == $bytes):
                // this case should never be reached, because we are in ASCII range
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0x7F & $bytes);

            case (0x07FF & $bytes) == $bytes:
                // return a 2-byte UTF-8 character
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0xC0 | (($bytes >> 6) & 0x1F))
                     . chr(0x80 | ($bytes & 0x3F));

            case (0xFFFF & $bytes) == $bytes:
                // return a 3-byte UTF-8 character
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0xE0 | (($bytes >> 12) & 0x0F))
                     . chr(0x80 | (($bytes >> 6) & 0x3F))
                     . chr(0x80 | ($bytes & 0x3F));
        }

        // ignoring UTF-32 for now, sorry
        return '';
    }

    public function printMsg($msg)
    {
        if ($this->debug) {
            echo $msg;
        }
    }

    public function throwException($msg)
    {
        throw new Exception($msg);
    }

    public function getStream()
    {
        while (empty($this->popStream))
        {

            $str = stream_get_contents($this->stream, 100);
            
            $this->popStream = str_split($str);
        }

        $popStream = array_shift($this->popStream);
        return $popStream;
    }

    public function checkStream()
    {
        $isEnd = true;
        if (!feof($this->stream) || !empty($this->popStream))
        {
            $isEnd = false;
        }
        return $isEnd;
    }
}


