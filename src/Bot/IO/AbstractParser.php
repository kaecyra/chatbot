<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Abstract Parser
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class AbstractParser implements ParserInterface, EventAwareInterface, LoggerAwareInterface, TaggedLogInterface {

    use EventAwareTrait;
    use LoggerBoilerTrait;
    use LoggerAwareTrait;
    use TaggedLogTrait;

    /**
     * Container
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

}
