<?php

/**
 * Voice - a Google Text-to-speech (gTTS) API
 *
 * Voice is an API that takes a text string and a language, and returns
 * an MP3 file with the spoken text. It outputs directly to your
 * browser, or prompts you to download the file.
 *
 * Example usage:
 * $voice = new Voice();
 * $voice->setLanguage('en_US')->setQuery('Hello world')->download();
 *
 * @package  Voice
 * @author   Kevin van der Burg <kevinvanderburg@gmail.com>
 * @version  1.0
 * @access   Public
 * @see      https://github.com/kevin-toscani/Voice
 *
 */

class Voice {

    /**
     * The Google Translate base URL; you may want to change the TLD
     * if .com is banned in your country.
     *
     * @const string _URL The Google Translate Base URL
     */
    const _URL = 'https://translate.google.com';

    /**
     * The path Google Translate uses to return the text-to-speech MP3 file
     *
     * @const string _PATH The Google Translate API path
     */
    const _PATH = '/translate_tts';

    /**
     * Google Translate likes a valid user agent, so let's give it one.
     *
     * @const string _USERAGENT A random user agent
     */
    const _USERAGENT = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13';

    /**
     * Internal encoding string to be used (defaulted to UTF-8)
     *
     * @var string $ie The internal encoding
     */
    private $ie = 'UTF-8';

    /**
     * The query text to be converted to speech
     *
     * @var string $q The query text
     */
    private $q;

    /**
     * The language code to use in speect
     * For a list of valid language codes, see:
     * https://cloud.google.com/speech-to-text/docs/languages
     *
     * @var string $tl The language code
     */
    private $tl;

    /**
     * [$total description] (defaulted to 1)
     * @var integer $total
     */
    private $total = 1;

    /**
     * [$idx description] (defaulted to 0)
     * @var integer $idx
     */
    private $idx = 0;

    /**
     * The total length of submitted text
     * Note: this should not be over 100.
     *
     * @var integer $textlen String length of query
     */
    private $textlen;

    /**
     * Google Translate requires a client, although any client
     * is accepted at this time.
     *
     * @var string $client Client name (defaults to 't')
     */
    private $client = 't';

    /**
     * Primary class constructor
     *
     * @param string $query    The text to be converted to speech
     * @param string $language The language code
     */
    public function __construct($query = null, $language = null) {
        $this->setQuery($query);
        $this->setLanguage($language);
    }

    /**
     * Sets the text to be converted to speech
     *
     * @param  string $query The text to be converted to speech
     * @return object The Voice object
     */
    public function setQuery($query) {
        $this->q = $query;
        $this->textlen = strlen($query);
        return $this;
    }

    /**
     * Sets the language to be used
     *
     * @param string The language to be used
     */
    public function setLanguage($language) {
        $this->tl = $language;
        return $this;
    }

    /**
     * Downloads the converted MP3 and exits the script
     *
     * @return void
     */
    public function download() {
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=" . $this->tl . '_' . preg_replace('/[^a-z0-9]/', '', strtolower($this->q)) . '.mp3');
        header("Content-Type: audio/x-mp3");
        header("Content-Transfer-Encoding: binary");
        echo $this->_retrieve(); exit;
    }

    /**
     * Sends the converted MP3 to the browser
     *
     * @return void
     */
    public function speak() {
        header('Content-type: audio/mpeg');
        echo $this->_retrieve(); exit;
    }

    /**
     * Generates the Google Translate link and retrieves
     * the resulting MP3 in a binary string
     *
     * @return string The binary MP3 data
     */
    private function _retrieve() {
        $args = [
            'ie'      => $this->ie,
            'q'       => $this->q,
            'tl'      => $this->tl,
            'total'   => $this->total,
            'idx'     => $this->idx,
            'textlen' => $this->textlen,
            'tk'      => $this->_generateTk(),
            'client'  => $this->client,
        ];

        $url = self::_URL . self::_PATH . '?' . http_build_query($args);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, self::_USERAGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        return $output;
    }

    /**
     * Helper function to generate a valid token, based on the
     * entered query.
     *
     * Ported from javascript, found at
     * https://stackoverflow.com/a/40078546
     *
     * @return string A valid token
     */
    private function _generateTk() {
        $tkk = self::_getTkk();

        $bytesArray = $this->_transformQuery();

        $d = explode('.', $tkk);
        $tkkIndex = 1 * $d[0];
        $tkkKey   = 1 * (isset($d[1]) ? $d[1] : 0);

        $encodingRound1 = array_reduce($bytesArray, function($acc, $current) {
            $acc += $current;
            return self::_slortsox($acc, ['+-a', '^+6']);
        }, $tkkIndex);

        $encodingRound2 = self::_slortsox($encodingRound1, ['+-3', '^+b', '+-f']) ^ $tkkKey;
        $normalizedResult = self::_normalizeHash($encodingRound2);
        return $normalizedResult . '.' . ($normalizedResult ^ $tkkIndex);
    }

    /**
     * Transforms the query text to a bytes array, used for encoding the token.
     *
     * @return [integer] Array of bytes
     */
    private function _transformQuery() {
        $e = [];
        $f = 0;

        for($g = 0; $g < $this->textlen; $g++) {
            $l = self::_charCode(substr($this->q, $g, 1));

            if($l < 128) {
                $e[$f++] = $l;
            }

            elseif($l < 2048) {
                $e[$f++] = $l >> 6 | 0xC0;
                $e[$f++] = $l & 0x3F | 0x80;
            }

            elseif(
                   0xD800 == ($l & 0xFC00)
                && $g + 1 < $this->textlen
                && 0xDC00 == (self::_charCode(substr($this->query, $g+1, 1)) & 0xFC00)
            ) {
                $l = (1 << 16) + (($l & 0x03FF) << 10) + (self::_charCode(substr($this->query, ++$g, 1)) & 0x03FF);
                $e[$f++] = $l >> 18 | 0xF0;
                $e[$f++] = $l >> 12 & 0x3F | 0x80;
                $e[$f++] = $l & 0x3F | 0x80;
            }

            else {
                $e[$f++] = $l >> 12 | 0xE0;
                $e[$f++] = $l >> 6 & 0x3F | 0x80;
                $e[$f++] = $l & 0x3F | 0x80;
            }
        }

        return $e;
    }

    /**
     * Retrieves a valid token key from the Google Translate server
     *
     * @return string A Google Translate token key
     */
    private static function _getTkk() {
        $ch = curl_init(self::_URL);
        curl_setopt($ch, CURLOPT_USERAGENT, self::_USERAGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);

        preg_match('/tkk:\'([0-9]+\.[0-9]+)\'/', $output, $result);
        return $result[1];
    }

    /**
     * Helper function to encode the token
     *
     * @param int $num
     * @param [string] $opArray array of encoding operations
     * @return int The encoded integer
     */
    private static function _slortsox($num, $opArray) {
        return array_reduce($opArray, function($acc, $opString) {
            $op1 = substr($opString, 1, 1);
            $op2 = substr($opString, 0, 1);
            $xd  = substr($opString, 2, 1);
            $shiftAmount = ($xd >= 'a') ? self::_charCode(substr($xd, 0, 1)) - 87 : 1 * $xd;
            $mask = ($op1 == '+') ? self::_urshift($acc, $shiftAmount) : $acc << $shiftAmount;
            return $op2 == '+' ? ($acc + $mask & 0xFFFFFFFF) : ($acc ^ $mask);
        }, $num);
    }

    /**
     * Bitwise unsigned right shift operator implementation
     *
     * Thanks to Joachim Isaksson at Stack Overflow
     * https://stackoverflow.com/a/14428473
     *
     * @param  integer $acc Integer to be shifted
     * @param  integer $shiftAmount Number of steps to shift
     * @return integer Unsigned right shifted integer
     */
    private static function _urshift($acc, $shiftAmount) {
        if($shiftAmount == 0) return $acc;
        return ($acc >> $shiftAmount) & ~(1 << (8 * PHP_INT_SIZE - 1) >> ($shiftAmount - 1));
    }

    /**
     * Helper function to normalize the generated hash
     *
     * @param  integer $encodingRound2 The original hash
     * @return integer The normalized hash
     */
    private static function _normalizeHash($encodingRound2) {
        if($encodingRound2 < 0) {
            $encodingRound2 = ($encodingRound2 & 0x7fffffff) + 0x80000000;
        }
        return $encodingRound2 % 1E6;
    }

    /**
     * PHP ord() doesn't cut it for UTF-16 character codes.
     * So I had to add a helper function to fix this.
     *
     * Thanks to nj_ at Stack Overflow
     * https://stackoverflow.com/a/40864086
     *
     * @param  string $char Input character
     * @return int The UTF-16 character code
     */
    private static function _charCode($char) {
        $converted = iconv('UTF-8', 'UTF-16LE', $char);
        return ord($converted[0]) + (ord($converted[1]) << 8);
    }
}
