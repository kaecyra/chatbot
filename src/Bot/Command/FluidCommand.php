<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

/**
 * Fluid command parser
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class FluidCommand extends AbstractCommand implements EventAwareInterface {

    use EventAwareTrait;

    /**
     * Initial input string
     * @var string
     */
    protected $inputstring;

    /**
     * Constructor
     *
     * @param string $input
     * @param array $initial optional
     */
    public function __construct(string $input, array $initial = []) {
        parent::__construct();

        if (!is_array($initial)) {
            $initial = [];
        }

        $this->setInputString($input, $initial);
    }

    /**
     * Get initial command string
     *
     * @return string
     */
    public function getInputString() {
        return $this->inputstring;
    }

    /**
     * Set initial command string
     *
     * This method also internally resets the state of the TextCommand so that it is
     * ready for parsing.
     *
     * @param string $inputString
     * @param array $initial
     * @return CommandInterface
     */
    public function setInputString(string $inputString, array $initial): CommandInterface {
        $this->inputstring = $inputString;
        $this->setCommand('');

        $defaults = [
            'targets' => [],
            'toggle' => null,
            'gather' => false,
            'consume' => false,
            'token' => null,
            'last_token' => null,
            'tokens' => 0,
            'parsed' => 0
        ];
        $this->data = array_merge($defaults, $initial);

        return $this;
    }

    /**
     * Test possible phrase on command string
     *
     * @param string $test
     * @param boolean $case optional. case sensitive. default false
     * @return boolean
     */
    public function match($test, $case = false): bool {
        $flags = '';
        if (!$case) {
            $flags = 'i';
        }
        return (boolean)preg_match("`{$test}`{$flags}", $this->getInputString());
    }

    /**
     * Test if state has token(s)
     *
     * @param array|string $tokens
     * @param boolean $all optional. require all tokens. default false (any token)
     */
    public function have($tokens, $all = false): bool {
        if (!is_array($this->data['pieces']) || !count($this->data['pieces'])) {
            return false;
        }

        if (!is_array($tokens)) {
            $tokens = [$tokens];
        }

        foreach ($tokens as $token) {
            if (in_array($token, $this->data['pieces'])) {
                if (!$all) {
                    return true;
                }
            } else {
                if ($all) {
                    return false;
                }
            }
        }

        // If we get here with $all, we have all tokens
        if ($all) {
            return true;
        }

        // If we get here, we don't have all and we got no tokens
        return false;
    }

    /**
     * Internal analyze method
     *
     * @return CommandInterface
     */
    public function analyze(): CommandInterface {

        $this->pieces = explode(' ', $this->getInputString());
        $this->parts = $this['pieces'];

        // Scan phase, allows gathering unprefixed tokens
        foreach ($this->parts as $part) {
            $this->fire('tokenscan', [$this, $part]);
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
                                $this->addTarget($loop['node'], $this->gather['delta'], $loop['multi']);
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
                            $this->addTarget($loop['node'], $checkPhrase, $loop['multi']);
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
                            $this->addTarget($loop['node'], $currentDelta, $loop['multi']);
                            break;
                        }
                        break;

                    // Hook for custom tokens
                    default:
                        $this->fire('tokengather', [$this, $loop]);
                        break;
                }

                if (!strlen($this->token)) {
                    $this->gather = false;
                    continue;
                }

            } else {

                // Fire stem gathering
                $this->fire('stems', [$this]);

                // Fire method gathering
                $this->fire('methods', [$this]);

                // Fire enhancements
                $this->fire('enhancements', [$this]);

                /*
                 * FOR, BECAUSE
                 */

                if (in_array($this->compare_token, ['for', 'because', 'that'])) {
                    $this->consumeUntilNextKeyword('for', false, true);
                }

                /*
                 * Allow consume overrides in plugins
                 */
                $this->fire('token', [$this]);

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
            $this->addTarget($loop['node'], $this->gather['delta']);
            $this->gather = false;
        }

        // Gather any remaining tokens into the 'gravy' field
        if ($this->getCommand()) {
            $gravy = array_slice($this->pieces, $this->tokens);
            $this->gravy = implode(' ', $gravy);
        }

        if ($this->consume) {
            $this->cleanupConsume($this);
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
     * Add a target
     *
     * @param string $name
     * @param mixed $data
     * @param boolean $multi optional. default false.
     */
    public function addTarget($name, $data, $multi = false) {
        // Prepare the target
        if ($multi) {
            if (isset($this->targets[$name])) {
                if (!is_array($this->targets[$name])) {
                    $this->targets[$name] = [$this->targets[$name]];
                }
            } else {
                $this->targets[$name] = [];
            }
            array_push($this->targets[$name], $data);
        } else {
            $this->targets[$name] = $data;
        }
    }

    /**
     * Test if we have a given target
     *
     * @param string $name
     * @return boolean
     */
    public function haveTarget($name): bool {
        return (isset($this->targets[$name]) && !empty($this->targets[$name]));
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

}