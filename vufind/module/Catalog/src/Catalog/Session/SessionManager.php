<?php

/**
 * VuFind SessionMananger
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\Session;

use function is_array;
use function is_string;

/**
 * Session manager with added forcing of consistent bot sessions
 * ids. This is too prevent bots from creating too many sessions
 * if they fail to pass their already created session id
 * back with subsequent requests.
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SessionManager extends \Laminas\Session\SessionManager
{
    /**
     * List of bot user agent strings to for a bot session for
     *
     * @var array
     */
    protected $botNames;

    /**
     * A salt for bot session ids
     *
     * @var array
     */
    protected $botSalt;

    /**
     * Start session override with bot protections
     *
     * @param bool $preserveStorage If set to true, current session storage will not be overwritten by the
     *                              contents of $_SESSION.
     *
     * @return void
     *
     * @throws Exception\RuntimeException
     */
    public function start($preserveStorage = false)
    {
        $this->botOverride();
        parent::start($preserveStorage);
    }

    /**
     * For bot requests (checked via user agent) coming from a given
     * IP address, always set the same session id. This can prevent
     * a poorly behaving bot from creating excess sessions by not
     * sending back the session id with subsequent requests.
     *
     * @return void
     */
    private function botOverride()
    {
        foreach ($this->botNames() as $bot) {
            if (
                str_contains(
                    mb_strtolower($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    mb_strtolower($bot)
                )
            ) {
                $this->setBotId($bot);
                return;
            }
        }
    }

    /**
     * Generate and set a deterministic session id for the given bot identifier
     * from the requested IP address. If a session salt is available, it
     * will be used in generating the session id.
     *
     * @param string $botName A string used to identify the bot
     *
     * @return void
     */
    private function setBotId($botName)
    {
        $botHash = base64_encode(
            hash('sha224', $botName . $_SERVER['REMOTE_ADDR'] . $this->botSalt(), true)
        );
        // Bot Forced Session prefix (btfs) can be used to identify these sessions
        $botId = 'btfs' . preg_replace('/[\W]/', '', $botHash);

        if (!$this->getId()) {
            $this->setId($botId);
        }
    }

    /**
     * Set which bot names to force a bot session for
     *
     * @param array $botNames The list of botnames
     *
     * @return void
     */
    public function setBotNames(array $botNames)
    {
        if (!is_array($botNames)) {
            throw new \Exception('Session bot names must be an array containing strings');
        }
        $this->botNames = $botNames;
    }

    /**
     * The salt used in creating bot session ids
     *
     * @param string $botSalt The salt string
     *
     * @return void
     */
    public function setBotSalt(string $botSalt)
    {
        if (!is_string($botSalt)) {
            throw new \Exception('Session bot salt must be a string');
        }
        $this->botSalt = $botSalt;
    }

    /**
     * Retrieve the bot names to match if set,
     * or an empty array if none are defined
     *
     * @return array
     */
    private function botNames()
    {
        return $this->botNames ?? [];
    }

    /**
     * Retrieve the bot salt if set,
     * or empty string if not defined.
     *
     * @return string
     */
    private function botSalt()
    {
        return $this->botSalt ?? '';
    }
}
