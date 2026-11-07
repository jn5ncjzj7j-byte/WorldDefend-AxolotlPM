<?php

declare(strict_types=1);

namespace axolotl\worlddefend;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class HomeCommand extends Command{

    public function __construct(private Main $plugin, string $name){
        parent::__construct($name, "Manage two personal home locations", "/" . $name);
        $this->setPermission("worlddefend.command.home");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
        if(!$sender instanceof Player){
            $sender->sendMessage("Run this command in-game.");
            return true;
        }
        if(!isset($args[0]) || !in_array($args[0], ["1", "2"], true)){
            $sender->sendMessage($this->plugin->message($this->getName() === "sethome" ? "usage-sethome" : "usage-home"));
            return true;
        }
        $slot = (int) $args[0];
        if($this->getName() === "sethome"){
            $this->plugin->saveHome($sender, $slot);
        }else{
            $this->plugin->teleportHome($sender, $slot);
        }
        return true;
    }
}
