<?php
/*
+---------------------------------------------------------------------------
|   MCPass - A minecraft HTTPConsole module for PHP-IRC
|   ========================================================
|   by ks07
|   (c) 2011 by http://ultimateminecraft.net/
|   irc: #game@irc.ultimateminecraft.net
|   ========================================
+---------------------------------------------------------------------------
|   > MCPass Mod
|   > Module written by ks07
|   > Module Version Number: 1.0
+---------------------------------------------------------------------------
|   > This module is free software; you can redistribute it and/or
|   > modify it under the terms of the GNU General Public License
|   > as published by the Free Software Foundation; either version 2
|   > of the License, or (at your option) any later version.
|   >
|   > This module is distributed in the hope that it will be useful,
|   > but WITHOUT ANY WARRANTY; without even the implied warranty of
|   > MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
|   > GNU General Public License for more details.
|   >
|   > You should have received a copy of the GNU General Public License
|   > along with this program; if not, write to the Free Software
|   > Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
+---------------------------------------------------------------------------
*/

class mcpass_mod extends module {

	public $title = "MCPass";
	public $author = "ks07";
	public $version = "1.0";

        private $allowednicks;
        private $allowedmodes;
        private $commandblacklist;
        private $channel;
        private $conhost;
        private $conport;

        // Loads the config on startup.
	public function init()
	{
            $this->mcpass_load();
	}

        // Loads the ini file.
        private function mcpass_load()
        {
            $INIcont = parse_ini_file("modules/mcpass/mcpass.ini");
            if ($INIcont === false) {
                $this->ircClass->privMsg($line['to'], "[ERROR]: Failed to load config!", $queue = 1);
                die();
            } else {
                $this->allowednicks = explode(" ", $INIcont['nicknames']);
                $this->allowedmodes = $INIcont['usermodes'];
                $this->commandblacklist = explode(" ", $INIcont['blacklist']);
                $this->channel = $INIcont['channel'];
                $this->conhost = $INIcont['conhost'];
                $this->conport = $INIcont['conport'];
            }
        }

        // Reloads the ini file.
        public function mcpass_reload($line, $args)
        {
            if ($this->mcpass_check_permitted($line['fromNick']))
            {
                $this->ircClass->privMsg($line['to'], "Reloading Config.", $queue = 1);
                $this->mcpass_load();
            } else {
                $this->ircClass->privMsg($line['to'], "Access denied!", $queue = 1);
            }
        }

        // Temporarily allows a nick to give commands until reload.
        public function mcpass_allow($line, $args)
        {
            if ($this->mcpass_check_permitted($line['fromNick']))
            {
                $this->ircClass->privMsg($line['to'], "Allowing nick " . $args['arg1'], $queue = 1);
                $this->allowednicks[] = $args['arg1'];
            } else {
                $this->ircClass->privMsg($line['to'], "Access denied!", $queue = 1);
            }
        }

        // Provides the !mcdo command and runs the actual command.
        public function mcpass_do($line, $args)
        {
            if ($this->mcpass_check_permitted($line['fromNick']) && $this->mcpass_check_blacklist($args['arg1']))
            {
                // Sends the given command string to the server, using PHP-IRC's built in functions.
                $Command = urlencode($args['query']);
                $Query = "command=" . $Command;
                $MCQuery = socket::generateGetQuery($Query, $this->conhost, "/console");
                $this->ircClass->addQuery($this->conhost, $this->conport, $MCQuery, $line, $this, "mcpass_return");
            } else {
                $this->ircClass->privMsg($line['to'], "Access denied!", $queue = 1);
            }
        }

        // Uses the PHP-IRC functions to handle the output of !mcdo
        public function mcpass_return($line, $args, $result, $response)
        {
            if ($result == "QUERY_SUCCESS")
            {
                $count = 0;
                foreach (explode("\n", $response) As $RespLine)
                {
                    $count++;
                    // Command response begins at line 8.
                    if ($count > 7)
                    {
                    $this->ircClass->privMsg($line['to'], $RespLine, $queue = 1);
                    }
                }
            } else {
                $this->ircClass->privMsg($line['to'], "[ERROR]: Failed to send/retrieve command!", $queue = 1);
            }
        }

        // Checks if the nick is permitted to perform commands.
        private function mcpass_check_permitted($user)
        {
            if (! $this->ircClass->hasModeSet($this->channel, $user, $this->allowedmodes))
            {
                return false;
            }
            foreach ($this->allowednicks as $nickname)
            {
                if ($nickname == $user)
                {
                    return true;
                }
            }
            return false;
        }

        // Checks if the command is blacklisted.
        private function mcpass_check_blacklist($command)
        {
            foreach ($this->commandblacklist as $bcom)
            {
                if ($bcom == $command)
                {
                    return false;
                }
            }
            return true;
        }

        // Temporarily revokes a nicks access until reload.
        public function mcpass_revoke($line, $args)
        {
            if ($this->mcpass_check_permitted($line['fromNick']))
            {
                $this->ircClass->privMsg($line['to'], "Revoking nick " . $args['arg1'], $queue = 1);
                $ArrayKey = array_search($args['arg1'], $this->allowednicks);
                unset($this->allowednicks[$ArrayKey]);
            } else {
                $this->ircClass->privMsg($line['to'], "Access denied!", $queue = 1);
            }
        }
}

?>
