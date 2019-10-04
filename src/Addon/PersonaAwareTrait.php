<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Addon;

use Kaecyra\ChatBot\Bot\Persona;

/**
 * Persona Aware Trait
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
trait PersonaAwareTrait {

    /**
     * Person
     * @var Persona
     */
    protected $persona;

    /**
     * Sets a persona.
     *
     * @param Persona $persona
     */
    public function setPersona(Persona $persona) {
        $this->persona = $persona;
    }

    /**
     * Get person.
     *
     * @return Persona
     */
    public function getPersona(): Persona {
        return $this->persona;
    }
}