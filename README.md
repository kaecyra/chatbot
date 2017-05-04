ChatBot
======

`chatbot` is a PHP powered asynchronous robot designed to join company instant messaging rooms and provide
useful and humorous commands and responses.

ChatBot is extensible, having the ability to load modules that modify and extend core functionality by intercepting events thrown using a simple event manager.

# Table of Contents
1. [Installation](#installation)  
2. [HipChat Integration](#hipchat-integration)
3. [Usage](#usage)  
4. [Core Commands](#core-commands)  
	1. Report
	2. List Modules
	3. Load Module
	4. Unload Module
5. [Extending ChatBot](#extending-chatbot)
	1. Module Initialization
	2. Hooks
	3. Permissions
	4. Core Events


# Installation

*ChatBot requires PHP 7.0 or higher and an open port 5222.*

Installing ChatBot is as simple as checking out this github repository, or downloading the zip file. Thereafter, copy `conf/config.json.tpl` to `conf/config.json` and make changes as needed.

A good place to start is probably adding `xmpp.jid` and `xmpp.pass`, and changing the bot's nickname.

# HipChat Integration

1. Create a new admin account and sign in to hipchat.com with it.
2. Go to {yoursite}.hipchat.com/account/xmpp
3. Use the Jabber ID (jid) and nick on this page in your bot config.
4. Start ChatBot. He should connect to your group.
5. Message ChatBot: `join room #room`.

# Usage

Once the bot is configured, start it using `./chatbot.php start`. When the bot is running, you can shut it down by running `./chatbot.php stop`. Simple!

ChatBot writes fairly aggressively to a log file, available by default at `log/chatbot.log`. Logs are rotated after 10mb automatically. Only 1 previous log file is retained.

# Core Commands

ChatBot has a core module called `base` that contains his "core" commands. These facilitate loading and unloading of modules.

## Report
*Challenge/response method for testing connectivity.*  

#### Syntax
> `chatbot report`

#### Response
> `<your name>: Reporting in, sir`

## List Modules
*List all available modules.*

Specifying either **active** or **enabled** somewhere in the line will cause the response to be limited to enabled modules only.

#### Syntax
> `chatbot list [active|enabled] modules`

#### Response
> ```
> <your name>, there are 14 modules enabled
>  base v1.0 by Tim Gunter <tim@vanillaforums.com> - Base Commands
>  xmpp v1.0 by Tim Gunter <tim@vanillaforums.com> - XMPP Module
>  xmppmuc v1.0 by Tim Gunter <tim@vanillaforums.com> - XMPP Multi User Chat module
> ```

## Load Module
*Load and activate an available module.*

Successfully loading a module causes it to be saved to the config file. It will be loaded automatically in the future whenever ChatBot is restarted.

#### Syntax
> `chatbot load module <module name>`

#### Response
> `Loaded: <module name> v<version> by <author> - <description>`

## Unload Module
*Unload and deactive an active module.*

Successfully unloading a module causes it to be removed from the config file. It will no longer be automatically loaded on boot.

#### Syntax
> `chatbot unload module <module name>`

#### Response
> `Unloaded: <module name> v<version>`

# Extending ChatBot

ChatBot supports extension via loadable modules. The `ROOT/modules` folder is automatically scanned by ChatBot during the boot sequence.

Modules should have their own folder. Inside that folder should be a PHP class file named `class.<modulename>.module.php` which should contain a PHP class named `<ModuleName>Module` that extends the module base class `\ChatBot\Module`.

Module class files should use `namespace Module;` in order to prevent name conflicts.

**Example**

```
namespace Module;

use \ChatBot\ChatBot;
use \ChatBot\Module;

class BaseModule extends Module {

}
```

## Module Initialization

The `\ChatBot\Module` base class contains a single abstract method, `start()` which acts as the module constructor. `__construct()` is private and cannot be directly used.

## Hooks

The `start()` method should be used to define module hooks. A hook definition is simply a `callable` array tied to an event name.

**Example**

```
public function start() {
    $this->hook('event_name', [$this, 'hook_method']);
}
```

Multiple hooks can be defined for each event.

## Permissions

In order to allow ChatBot to support higher level privileged functions, integration with Orchestration is supported. This integration allows role based permissions to be used for each command.

Each module can define a mapping between roles and granted permissions in its `start()` method.

**Example**

```
public function start() {
    $this->roles = [
        'administrator' => [
            'base.module.control'
        ],
        'staff' => [
            'base.report'
        ]
    ];
}
```

This mapping grants the permission `base.module.control` to users with the **Administrator** role, and the permission `base.report` to users with the **Staff** role.

## Core Events

ChatBot has a number of core events that can be hooked out of the box. Combining these events can allow module writers to produce complex workflows.

- startup
- tick
- message
- directed
- command

### Event: startup
Fired on startup, after all modules are loaded and just before ChatBot connects to the chat service.

**Arguments**
> No arguments are provided.

### Event: tick
Fired every 10 seconds while ChatBot is running.

**Arguments**
> No arguments are provided.

### Event: message
Fired every time ChatBot receives any private or groupchat message.

**Arguments**
> `Array $event`
> 
> ```
> An array describing the received message event.
> [
>   'scope' => String: 'chat' or 'group',
>   'original' => XMPPMsg: message,
>   'to' => XMPPJid: message source JID,
>   'from' => XMPPJid: message target JID,
>   'body' => String: body text,
>   'type' => ?,
>   'fromuser' => Array: source user info array
> ]
> ```

### Event: directed
Fired every time ChatBot receives a private or groupchat message that was directed at the bot. All private messages are considered directed, and groupchat messages that start or end with the bot's name are also considered directed.

**Arguments**
> `Array $event`
> 
> ```
> Same as message
> ```

### Event: command
Fired when chatbot receives a message that triggers a State-matched command.

**Arguments**
> `Array $event`
> 
> ```
> Same as message
> ```
** **
> `State $state`
> 
> ```
> An object describing the triggered command and providing access to its data.
> ```

