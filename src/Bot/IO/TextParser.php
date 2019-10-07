<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO;

use Kaecyra\ChatBot\Bot\Command\CommandInterface;

/**
 * Text parser
 *
 * This object implements ArrayAccess so that most of its contents can be accessed
 * as an array.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class TextParser extends AbstractParser implements \ArrayAccess {

    /**
     * Command supplemental data
     * @var array
     */
    protected $data;

    /**
     * Internal analyze method
     *
     * @param CommandInterface $command
     * @param MessageWrapper $message
     * @return TextParser
     */
    public function parse(CommandInterface $command, MessageWrapper $message): array {

        $this->pieces = explode(' ', $this->getInputString());
        $this->parts = $this['pieces'];

        // Scan phase, allows gathering unprefixed tokens
        foreach ($this->parts as $part) {
            $command->fire('tokenscan', [$this, $part]);
        }

        $this->nextToken();

        while ($this->token !== null) {

            // GATHER

            if ($this->gathering()) {

                $loop = [];

                $loop['node'] = valr('gather.node', $this);
                $loop['type'] = strtolower(valr('gather.type', $this, $loop['node']));
                $loop['multi'] = valr('gather.multi', $this, false);

                $loop['firstpass'] = valr('gather.first_pass', $this, true);
                $this->gather['first_pass'] = false;

                if (isset($this->gather['fast'])) {
                    unset($this->gather['fast']);
                }

                // Detect boundaries
                $boundaries = valr('gather.boundary', $this, null);
                if ($boundaries) {
                    if (!is_array($boundaries)) {
                        $boundaries = [$boundaries];
                    }

                    if (count($boundaries)) {
                        foreach ($boundaries as $boundary) {
                            if ($this->token == $boundary) {
                                $command->addTarget($loop['node'], $this->gather['delta'], $loop['multi']);
                                $this->gather = false;
                                $loop['type'] = false;
                                break;
                            }
                        }
                    }
                }

                switch ($loop['type']) {

                    case 'phrase':

                        $terminators = ['"' => true];
                        $terminator = $this->checkTerminator((($loop['firstpass']) ? $terminators : null));

                        // Add space if there's something in Delta already
                        if (strlen($this->gather['delta'])) {
                            $this->gather['delta'] .= ' ';
                        }
                        $this->gather['delta'] .= $this->token;
                        $this->consume();

                        // Check if this is a phrase
                        $isList = val('list', $this->gather, false);
                        $terminator = val('terminator', $this->gather, false);
                        if (!$terminator && strlen($this->gather['delta'])) {
                            $checkPhrase = trim($this->gather['delta']);

                            // Allow lists
                            if ($isList && substr($this->token, -1) == ',') {
                                break;
                            }

                            $checkPhrase = trim($this->gather['delta']);
                            $this->gather = false;
                            $command->addTarget($loop['node'], $checkPhrase, $loop['multi']);
                            break;
                        }
                        break;

                    case 'page':
                    case 'number':

                        // Add token
                        if (strlen($this->gather['delta'])) {
                            $this->gather['delta'] .= ' ';
                        }
                        $this->gather['delta'] .= $this->token;
                        $this->consume();

                        // If we're closed, close up
                        $currentDelta = trim($this->gather['delta']);
                        if (strlen($currentDelta) && is_numeric($currentDelta)) {
                            $this->gather = false;
                            $command->addTarget($loop['node'], $currentDelta, $loop['multi']);
                            break;
                        }
                        break;

                    case 'word':

                        // Add token
                        if (strlen($this->gather['delta'])) {
                            $this->gather['delta'] .= ' ';
                        }
                        $this->gather['delta'] .= $this->token;
                        $this->consume();

                        // If we're closed, close up
                        $currentDelta = trim($this->gather['delta']);
                        if (strlen($currentDelta)) {
                            $this->gather = false;
                            $command->addTarget($loop['node'], $currentDelta, $loop['multi']);
                            break;
                        }
                        break;

                    // Hook for custom tokens
                    default:
                        $command->fire('tokengather', [$this, $loop]);
                        break;
                }

                if (!strlen($this->token)) {
                    $this->gather = false;
                    continue;
                }

            } else {

                // Fire stem gathering
                $command->fire('stems', [$this]);

                // Fire method gathering
                $command->fire('methods', [$this]);

                // Fire enhancements
                $command->fire('enhancements', [$this]);

                /*
                 * FOR, BECAUSE
                 */

                if (in_array($this->compare_token, ['for', 'because', 'that'])) {
                    $this->consumeUntilNextKeyword('for', false, true);
                }

                /*
                 * Allow consume overrides in plugins
                 */
                $command->fire('token', [$this]);

                // Allow fast gathering!
                if ($this->gathering() && val('fast', $this->gather)) {
                    continue;
                }

                /*
                 * Consume keywords into current cache if one exists
                 */
                $this->consumeUntilNextKeyword();
            }

            // Get a new token
            $this->nextToken();

            // End token loop
        }

        unset($this['parts']);

        /*
         * PARAMETERS
         */

        // Terminate any open gathers
        if ($this->gather) {
            $loop['node'] = $this->gather['node'];
            $command->addTarget($loop['node'], $this->gather['delta']);
            $this->gather = false;
        }

        // Gather any remaining tokens into the 'gravy' field
        if ($command->getCommand()) {
            $gravy = array_slice($this->pieces, $this->tokens);
            $this->gravy = implode(' ', $gravy);
        }

        if ($this->consume) {
            $this->cleanupConsume();
        }

        // Parse this resolved state into potential actions
        $this->parseFor();

        return $this;
    }

    /**
     * Are we gathering?
     *
     * @return boolean
     */
    public function gathering(): bool {
        return ($this->gather && !empty($this->gather['node']));
    }

    /**
     * Advance the token
     *
     */
    public function nextToken() {
        $this->last_token = $this->token;
        $this->token = array_shift($this->parts);
        $this->peek = val(0, $this->parts, null);
        $this->compare_token = preg_replace('/[^\w]/i', '', strtolower($this->token));
        if ($this->token) {
            $this->parsed++;
        }
    }

    /**
     * Check for and handle terminators
     *
     * @param array $terminators
     */
    public function checkTerminator($terminators = null) {

        // Detect termination
        $terminator = val('terminator', $this->gather, false);

        if (!$terminator && is_array($terminators)) {
            $testTerminator = substr($this->token, 0, 1);
            if (array_key_exists($testTerminator, $terminators)) {
                $terminator = $testTerminator;
                $this->token = substr($this->token, 1);
                $double = $terminators[$testTerminator];
                if ($double) {
                    $this->gather['terminator'] = $testTerminator;
                }
            }
        }

        if ($terminator) {
            // If a terminator has been registered, and the first character in the token matches, chop it
            if (!strlen($this->gather['delta']) && substr($this->token, 0, 1) == $terminator) {
                $this->token = substr($this->token, 1);
            }

            // If we've found our closing character
            if (($foundPosition = stripos($this->token, $terminator)) !== false) {
                $this->token = substr($this->token, 0, $foundPosition);
                unset($this->gather['terminator']);
            }
        }

        return val('terminator', $this->gather, false);
    }

    /**
     * Consume a token
     *
     * @param string $setting optional.
     * @param mixed $value optional.
     * @param boolean $multi optional. consume multiple instances of this token. default false.
     */
    public function consume($setting = null, $value = null, $multi = false) {
        // Clean up consume in case we're currently running one
        $this->cleanupConsume();

        $this->tokens = $this->parsed;
        if (!is_null($setting)) {
            // Prepare the target
            if ($multi) {
                if (isset($this->$setting)) {
                    if (!is_array($this->$setting)) {
                        $this->$setting = [$this->$setting];
                    }
                } else {
                    $this->$setting = [];
                }
                array_push($this->$setting, $value);
            } else {
                $this->$setting = $value;
            }
        }

        // If we consume a method, discard any stem
        if ($setting == 'method') {
            unset($this->data['stem']);
        }
    }

    /**
     * Consume tokens until we encounter the next keyword
     *
     * @param string $setting optional. start new consumption
     * @param boolean $inclusive whether to include current token or skip to the next
     * @param boolean $multi create multiple entries if the same keyword is consumed multiple times?
     */
    public function consumeUntilNextKeyword($setting = null, $inclusive = false, $multi = false) {

        if (!is_null($setting)) {

            // Cleanup existing Consume
            if ($this->consume !== false) {
                $this->cleanupConsume();
            }

            // What setting are we consuming for?
            $this->consume = [
                'setting' => $setting,
                'cache' => '',
                'multi' => $multi,
                'skip' => $inclusive ? 0 : 1
            ];

            // Prepare the target
            if ($multi) {
                if (isset($this->$setting)) {
                    if (!is_array($this->$setting)) {
                        $this->$setting = [$this->$setting];
                    }
                } else {
                    $this->$setting = [];
                }
            }

            // Never include the actual triggering keyword
            return;
        }

        if ($this->consume !== false) {
            // If Tokens == Parsed, something else already consumed on this run, so we stop
            if ($this->tokens == $this->parsed) {
                $this->cleanupConsume();
                return;
            } else {
                $this->tokens = $this->parsed;
            }

            // Allow skipping tokens
            if ($this->consume['skip']) {
                $this->consume['skip']--;
                return;
            }

            $this->consume['cache'] .= "{$this->token} ";
        }
    }

    /**
     * Cleanup consume
     *
     */
    protected function cleanupConsume() {
        if (!$this->consume) {
            return;
        }

        $setting = $this->consume['setting'];
        if ($this->consume['multi']) {
            array_push($this->$setting, trim($this->consume['cache']));
        } else {
            $this->$setting = trim($this->consume['cache']);
        }
        $this->consume = false;
    }

    /**
     * Parse the 'for' keywords into Time and Reason keywords as appropriate
     *
     */
    public function parseFor() {
        if (!isset($this->for)) {
            return;
        }

        $reasons = [];
        $unset = [];
        $fors = sizeof($this->for);
        for ($i = 0; $i < $fors; $i++) {
            $for = $this['for'][$i];
            $tokens = explode(' ', $for);
            if (!sizeof($tokens)) {
                continue;
            }

            // Maybe this is a time! Try to parse it
            if (is_numeric($tokens[0])) {
                $haveTime = false;
                $currentSpan = [];
                $goodSpan = [];
                foreach ($tokens as $forToken) {
                    $currentSpan[] = $forToken;
                    $forString = implode(' ', $currentSpan);

                    if (($time = strtotime("+{$forString}")) !== false) {
                        $haveTime = true;
                        $goodSpan[] = $forToken;
                        $this->time = implode(' ', $goodSpan);
                    }
                }

                if ($haveTime) {
                    $unset[] = $i;
                    continue;
                }
            }

            // Nope, its (part of) a reason
            $unset[] = $i;
            $reasons[] = $for;
        }

        $this->reason = rtrim(implode(' for ', $reasons), '.');

        // Delete parsed elements
        foreach ($unset as $unsetKey) {
            unset($this->for[$unsetKey]);
        }
    }

    /**
     * Get token/piece by index
     *
     * @param integer $index
     * @return string|null
     */
    public function index($index) {
        return $this->data['pieces'][$index] ?? null;
    }

    /**
     * Get data by key
     *
     * @param string $key
     */
    public function &__get ($key) {
        return $this->data[$key];
    }

    /**
     * Assigns value by key
     *
     * @param string $key
     * @param mixed $value value to set
     */
    public function __set($key, $value) {
        $this->data[$key] = $value;
    }

    /**
     * Whether or not data exists by key
     *
     * @param string $key to check for
     * @return boolean
     */
    public function __isset($key) {
        return isset($this->data[$key]);
    }

    /**
     * Unset data by key
     *
     * @param string $key
     */
    public function __unset($key) {
        unset($this->data[$key]);
    }

    /**
     * Check if offset exists
     *
     * @param mixed $offset
     */
    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    /**
     * Set value on offset
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Get offset value
     *
     * @param mixed $offset
     */
    public function offsetGet($offset) {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * Unset offset
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }

}