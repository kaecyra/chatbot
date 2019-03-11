<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;
use Kaecyra\ChatBot\Bot\DestinationInterface;
use Kaecyra\ChatBot\Bot\UserInterface;

/**
 * User Destination
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class UserDestination {

    /**
     * @var UserInterface
     */
    protected $user;

    /**
     * @var DestinationInterface
     */
    protected $destination;

    /**
     * @var string
     */
    protected $id;

    /**
     * UserDestination constructor
     *
     * @param UserInterface $user
     * @param DestinationInterface $destination
     */
    public function __construct(UserInterface $user, DestinationInterface $destination) {
        $this->user = $user;
        $this->destination = $destination;
        $this->id = sprintf('%s/%s', $user->getID(), $destination->getID());
    }

    /**
     *
     * @return string
     */
    public function getKey(): string {
        return $this->id;
    }

}