<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO\TypedToken;

use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Abstract Typed Token
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @version 1.0
 */
abstract class AbstractTypedToken implements TypedTokenInterface, LoggerAwareInterface, TaggedLogInterface {

    use LoggerAwareTrait;
    use TaggedLogTrait;
    use LoggerBoilerTrait;

    /**
     * @inheritDoc
     * @throws \ReflectionException
     */
    public function getFormatToken(): string {
        $shortClassName = strtolower((new \ReflectionClass($this))->getShortName());
        return "{$shortClassName}:{$this->getExample()}";
    }

    /**
     * NOOP flush
     *
     * @return mixed|void
     */
    public function flush() {

    }

}