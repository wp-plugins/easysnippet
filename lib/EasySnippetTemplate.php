<?php
/*
Copyright (c) 2010-2012 Box Hill LLC
*/

/** @noinspection PhpInconsistentReturnPointsInspection */
class EasySnippetTemplate {
    const TEXT = 1;
    const PAGE = 2;
    const FILE = 3;
    const VARIABLEREPLACE = 1;
    const CLASSREPLACE = 2;
    const REPEATREPLACE = 3;
    const INCLUDEIF = 4;

    const PRESERVEWHITESPACE = 1;
    const PRESERVECOMMENTS = 2;
    private $inText;
    private $opText;
    private $delimiter = '#';
    private static $translate = false;
    private static $textDomain = '';

    /**
     * Process a template file or text doing variable replacements and repeated elements
     * @param string $file
     * @param int $type
     */
    function __construct($file = '', $type = self::PAGE) {
        if ($file == '') {
            $this->inText = '';
            return;
        }
        /**
         * Figure out what sort of input we have
         * DOS line endings are converted to UNIX
         */
        switch ($type) {
            /**
             * TEXT is just a string
             */
            case self::TEXT :
                $this->inText = $file;
                break;

            /**
             * FILE is a text file
             */
            case self::FILE :
                $this->inText = @file_get_contents($file);
                if ($this->inText === false) {
                    trigger_error("Can't open file: '$file'", E_USER_NOTICE);
                    $this->inText = '';
                    return;
                }
                break;

            /**
             * PAGE is a V4 template file which should have START PAGE and END PAGE lines
             */
            case self::PAGE :
                $page = @file_get_contents($file);
                if ($page === false) {
                    trigger_error("Can't open page: '$file'", E_USER_NOTICE);
                    $this->inText = '';
                    return;
                }

                /**
                 * Figure out where the start and end of the page is
                 */

                $startPage = '<!-- START PAGE -->';
                $endPage = '<!-- END PAGE -->';
                $startPosition = strpos($page, $startPage);
                if ($startPosition === false) {
                    trigger_error("START PAGE not found in $file", E_USER_NOTICE);
                    return;
                }
                $endPosition = strpos($page, $endPage);
                if ($endPosition === false) {
                    trigger_error("END PAGE not found in $file", E_USER_NOTICE);
                    return;
                }
                $startPosition += strlen($startPage);
                $page = substr($page, $startPosition, $endPosition - $startPosition);
                /**
                 * See if we have any IGNORES and remove them and their contents if we do
                 */
                $startIgnore = '<!-- START IGNORE -->';
                $endIgnore = '<!-- END IGNORE -->';
                $endIgnoreLength = strlen($endIgnore);
                while (($startPosition = strpos($page, $startIgnore)) !== false) {
                    $endPosition = strpos($page, $endIgnore);
                    if ($endPosition === false) {
                        trigger_error("END IGNORE not found in $file", E_USER_NOTICE);
                        return;
                    }
                    $page = substr($page, 0, $startPosition) . substr($page, $endPosition + $endIgnoreLength);
                }

                $this->inText = $page;
                break;
        }
        $this->inText = str_replace("\r\n", "\n", $this->inText);
        $this->inText = str_replace("\r", "", $this->inText);
    }

    static function setTranslate($textDomain) {
        self::$translate = true;
        self::$textDomain = $textDomain;
    }

    private function cleanWhitespace($html, $options) {
        if (($options & self::PRESERVECOMMENTS) == 0) {
            $html = preg_replace('/<!-- .*? -->/', '', $html);
        }
        if (($options & self::PRESERVEWHITESPACE) == 0) {
            $lines = explode("\n", $html);
            $html = '';
            foreach ($lines as $line) {
                if (($trimmed = trim($line)) != '') {
                    $html .= "$trimmed ";
                }
            }
        }
        return $html;
    }

    function getTemplateHTML($options = 0) {
        return $this->cleanWhitespace($this->inText, $options);
    }

    /**
     * Process the template text
     *
     * @param null $data
     * @param int $options
     * @return mixed|string
     */
    function replace($data = null, $options = 0) {
        /**
         * If we have replacements, pre-process the template and remove conditionally INCLUDEd stuff
         */
        if ($data === null) {
            $data = new stdClass();
        }

        $currentPosition = 0;
        $this->opText = '';
        $inText = $this->inText;
        $firstType = null;
        /**
         * We return from within this loop when we have nothing left to process
         */
        while (true) {
            /**
             * Look for stuff to replace and find the first of them
             */
            $firstPosition = strlen($inText);
            $varPosition = strpos($inText, $this->delimiter, $currentPosition);
            if ($varPosition !== false) {
                $firstPosition = $varPosition;
                $firstType = self::VARIABLEREPLACE;
            }
            $repeatPosition = strpos($inText, '<!-- START REPEAT ', $currentPosition);
            if ($repeatPosition !== false && $repeatPosition < $firstPosition) {
                $firstPosition = $repeatPosition;
                $firstType = self::REPEATREPLACE;
            }

            $includeifPosition = strpos($inText, '<!-- START INCLUDEIF ', $currentPosition);
            if ($includeifPosition !== false && $includeifPosition < $firstPosition) {
                $firstPosition = $includeifPosition;
                $firstType = self::INCLUDEIF;
            }

            /**
             * If there's nothing to do, just return what we've got
             */
            if ($firstPosition == strlen($inText)) {
                $this->opText .= substr($inText, $currentPosition);
                if (!self::$translate || !class_exists('EasyDOMDocument')) {
                    return $this->cleanWhitespace($this->opText, $options);
                }
                $doc = new EasySnippetDOMDocument($this->opText, true);
                if (!$doc) {
                    return $this->opText;
                }

                $xlates = $doc->getElementsByClassName('xlate');

                if (count($xlates) == 0) {
                    return $this->cleanWhitespace($this->opText, $options);
                }

                // FIXME - use gettext if no __
                foreach ($xlates as $xlate) {
                    $original = $doc->innerHTML($xlate);
                    $translation = __($original, self::$textDomain);
                    if ($translation != $original) {
                        $xlate->nodeValue = $translation;
                    }
                }
                $html = $doc->getHTML(true);
                return $this->cleanWhitespace($html, $options);
            }

            /**
             * Copy over everything up to the first thing we need to process
             */
            $length = $firstPosition - $currentPosition;
            $this->opText .= substr($inText, $currentPosition, $length);
            $currentPosition = $firstPosition;
            /**
             * Get the thing to be replaced
             */
            switch ($firstType) {
                /**
                 * INCLUDEIF includes the code up to the matching END INCLUDEIF:
                 *  IF the condition variable exists and it's not false or null
                 */
                case self::INCLUDEIF :
                    /**
                     * Get the conditional.
                     * Only check a smallish substring for efficiency
                     * This limits include condition names to 20 characters
                     */
                    $subString = substr($inText, $currentPosition, 60);

                    if (preg_match('/<!-- START INCLUDEIF (!?)([_a-z][_0-9a-z]{0,31}) -->/i', $subString, $regs)) {
                        $negate = $regs[1];
                        $trueFalse = $negate != '!';
                        $includeCondition = $regs[2];
                    } else {
                        trigger_error("Malformed START INCLUDEIF at $currentPosition ($subString)", E_USER_NOTICE);
                        $this->opText .= "<";
                        $currentPosition++;
                        continue;
                    }
                    $endInclude = "<!-- END INCLUDEIF $negate$includeCondition -->";

                    $endIncludeLength = strlen($endInclude);
                    $endPosition = strpos($inText, $endInclude);
                    if ($endPosition == false) {
                        trigger_error("'$endInclude' not found", E_USER_NOTICE);
                        $this->opText .= "<";
                        $currentPosition++;
                        break;
                    }

                    /**
                     * If the condition is met, just remove the INCLUDEIF comments
                     * If the condition isn't met, remove everything up to the END INCLUDEIF
                     * The condition must be present, and NOT false or NULL
                     */
                    $condition = isset($data->$includeCondition) && $data->$includeCondition !== false && $data->$includeCondition !== null;

                    if ($condition === $trueFalse) {
                        $startInclude = "<!-- START INCLUDEIF $negate$includeCondition -->";
                        $startIncludeLength = strlen($startInclude);
                        $inText = substr($inText, 0, $currentPosition) . substr($inText, $currentPosition + $startIncludeLength, $endPosition - $currentPosition - $startIncludeLength) . substr($inText, $endPosition + $endIncludeLength);
                    } else {
                        $inText = substr($inText, 0, $currentPosition) . substr($inText, $endPosition + $endIncludeLength);
                    }
                    break;

                /**
                 * A variable is a valid PHP variable name (limited to 20 chars) between delimiters
                 * If we don't find a valid name, copy over the delimiter and continue
                 * FIXME - fall back to caller's vars if it doesn't exist
                 */
                case self::VARIABLEREPLACE :
                    $s = substr($inText, $currentPosition, 34);
                    if (!preg_match("/^$this->delimiter([_a-z][_0-9a-z]{0,31})$this->delimiter/si", $s, $regs)) {
                        $this->opText .= $this->delimiter;
                        $currentPosition++;
                        continue;
                    }
                    /**
                     * If we don't have a match for the variable, just assume it's not what we wanted to do
                     * so put the string we matched back into the output and continue from the trailing delimiter
                     */
                    $varName = $regs[1];
                    if (!isset($data->$varName)) {
                        $this->opText .= $this->delimiter . $varName;
                        $currentPosition += strlen($varName) + 1;
                        continue;
                    }
                    /**
                     * Got a match - replace the <delimiter>...<delimiter> with the vars stuff
                     * We *could* pass this on for recursive processing, but it's not something we would normally want to do
                     * Maybe have a special naming convention for vars we want to do recursively?
                     */
                    $this->opText .= $data->$varName;
                    $currentPosition += strlen($varName) + 2;
                    break;

                /**
                 * We've seen a start repeat.
                 * Find the name of the repeat (limited to 20 chars)
                 * If we can't find the name, assume it's not what we want and continue
                 *
                 * Look for a valid START REPEAT in the next 45 characters
                 */
                case self::REPEATREPLACE :
                    $s = substr($inText, $currentPosition, 45);
                    if (!preg_match('/<!-- START REPEAT ([_a-zA-Z][_0-9a-zA-Z]{0,19}) -->/m', $s, $regs)) {
                        $this->opText .= '<';
                        $currentPosition++;
                        continue;
                    }
                    $rptName = $regs[1];
                    /**
                     * Make sure we have a matching key and it's an array
                     */
                    if (!isset($data->$rptName) || !is_array($data->$rptName)) {
                        $this->opText .= '<';
                        $currentPosition++;
                        continue;
                    }

                    /**
                     * Now try to find the end of this repeat
                     */
                    $currentPosition += strlen($rptName) + 22;
                    $rptEnd = strpos($inText, "<!-- END REPEAT $rptName -->", $currentPosition);
                    if ($rptEnd === false) {
                        $this->opText .= '<!-- START REPEAT $rptName -->';
                        trigger_error("END REPEAT not found for $rptName", E_USER_NOTICE);
                        continue;
                    }

                    /**
                     * Do the repeat processing.
                     * For each item in the repeated array, process as a new template
                     */
                    $rptLength = $rptEnd - $currentPosition;
                    $rptString = substr($inText, $currentPosition, $rptLength);
                    $rptVars = $data->$rptName;
                    for ($i = 0; $i < count($rptVars); $i++) {
                        $saveTranslate = self::$translate;
                        self::$translate = false;
                        $rpt = new EasySnippetTemplate($rptString, self::TEXT, $this->delimiter);
                        $this->opText .= $rpt->replace($rptVars[$i], $options);
                        self::$translate = $saveTranslate;
                    }
                    /**
                     * Step over the end repeat
                     */
                    $currentPosition += strlen($rptName) + $rptLength + 20;
                    break;
            }
        }
        return '';
    }

}