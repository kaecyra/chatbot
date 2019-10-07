<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Core bot persona
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Persona implements LoggerAwareInterface, EventAwareInterface, TaggedLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;
    use EventAwareTrait;

    /**
     * Get an addressed delay message
     *
     * @param UserInterface $user
     * @param DestinationInterface $destination
     * @param string $operation
     * @return string
     */
    public function getAddressedDelayMessage(UserInterface $user, DestinationInterface $destination, string $operation): string {
        $bacon = $this->getRandom([
            "Hang in there {mention}, I'm still working on that {event}.",
            'This is taking a while {mention}, but everything is fine and nothing is ruined. Especially not this {event}.',
            "Go get a coffee {mention}, that {event} is taking its sweet time.",
            'Give it a second {mention}, your {event} is going to space for crying out loud!',
            'Good things come to those who wait {mention}. You like good things, right? Your {event} is still running.',
            'Did I leave a {event} in the oven {mention}?',
            "Rome wasn't built in a day {mention}, and neither is this {event}.",
            'Cool your jamjets {mention}, your {event} will be done... eventually.',
            "This {event} is still red in the middle {mention}, needs a bit more time.",
        ]);
        $mention = $this->getUserReference($user);
        return str_replace(['{mention}', '{event}'], [$mention, $operation], $bacon);
    }

    /**
     * Get an adddressed normal message
     *
     * @param UserInterface $user
     * @param DestinationInterface $destination
     * @param string $message
     * @return string
     */
    public function getAddressedMessage(UserInterface $user, DestinationInterface $destination, string $message): string {
        $bacon = $this->getRandom([
            "{mention}: {message}.",
        ]);
        $mention = $this->getUserReference($user);
        return str_replace(['{mention}', '{message}'], [$mention, $message], $bacon);
    }

    /**
     * Get an addressed error message
     *
     * @param UserInterface $user
     * @param DestinationInterface $destination
     * @param string $error
     * @return string
     */
    public function getAddressedError(UserInterface $user, DestinationInterface $destination, string $error): string {
        $bacon = $this->getRandom([
            "{mention}: {error}. Sorry.",
            "Uh {mention}, {error}. Sorry homie.",
            "Sorry {mention}, {error}.",
            "{mention}... {error}. Woops!",
            "Bad news {mention}, {error}",
            "Yikes {mention}, {error}. C'est la vie.",
            "{mention} you donut, {error}.",
            "Damn it {mention}, {error}!",
        ]);
        $mention = $this->getUserReference($user);
        return str_replace(['{mention}', '{error}'], [$mention, preg_replace('/^i([\' ])/', 'I$1', lcfirst($error))], $bacon);
    }

    /**
     * Get an addressed confirmation message
     *
     * @param UserInterface $user
     * @param DestinationInterface $destination
     * @param string $command
     * @return string
     */
    public function getAddressedConfirm(UserInterface $user, DestinationInterface $destination, string $command): string {
        $bacon = $this->getRandom([
            "{mention} you asked me to {command}. Ready?",
        ]);
        $mention = $this->getUserReference($user);
        return str_replace(['{mention}', '{command}'], [$mention, preg_replace('/^i([\' ])/', 'I$1', lcfirst($command))], $bacon);
    }

    /**
     * Get an addressed acknowledge message
     *
     * @param UserInterface $user
     * @param DestinationInterface $destination
     * @param string $message
     * @return string
     */
    public function getAddressedAcknowledge(UserInterface $user, DestinationInterface $destination, string $message): string {
        $bacon = $this->getRandom([
            "Ok {mention}, {message}.",
            "No problem {mention}, {message}.",
            "You got it {mention}, {message}.",
            "Sure thing {mention}, {message}.",
        ]);
        $mention = $this->getUserReference($user);
        return str_replace(['{mention}', '{message}'], [$mention, preg_replace('/^i([\' ])/', 'I$1', lcfirst($message))], $bacon);
    }

    /**
     * Get an addressed completion message
     *
     * @param UserInterface $user
     * @param DestinationInterface $destination
     * @param string $message
     * @return string
     */
    public function getAddressedComplete(UserInterface $user, DestinationInterface $destination, string $message): string {
        $bacon = $this->getRandom([
            "Great news {mention}, {message}.",
            "Good news {mention}, {message}.",
            "You can breathe again {mention}, {message}.",
            "FYI {mention}, {message}.",
            "P.S. {mention}, {message}",
            "Hey {mention}, just letting you know {message}",
        ]);
        $mention = $this->getUserReference($user);
        return str_replace(['{mention}', '{message}'], [$mention, preg_replace('/^i([\' ])/', 'I$1', lcfirst($message))], $bacon);
    }

    /**
     * Get a random response
     *
     * @param array $responses
     * @return string
     */
    protected function getRandom(array $responses): string {
        $countResponses = count($responses);
        $responseID = mt_rand(0, $countResponses - 1);
        return $responses[$responseID];
    }

    /**
     * Get a user mention'
     *
     * @param UserInterface $user
     * @return string
     */
    protected function getUserReference(UserInterface $user): string {
        return "@{$user->getName()}";
    }
}
