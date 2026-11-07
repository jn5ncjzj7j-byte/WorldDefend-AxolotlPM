<?php

declare(strict_types=1);

namespace axolotl\worlddefend;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class LobbyCommand extends Command{

    public function __construct(private Main $plugin, string $name, array $aliases = []){
        parent::__construct($name, "Teleport between lobby and main world", "/" . $name);
        $this->setAliases($aliases);
        $this->setPermission("worlddefend.command.teleport");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
        if(!$sender instanceof Player){
            $sender->sendMessage("Run this command in-game.");
            return true;
        }
        $world = $this->getName() === "play"
            ? (string) $this->plugin->getConfig()->get("main-world", "world")
            : (string) $this->plugin->getConfig()->get("lobby-world", "lobby");
        $this->plugin->beginTeleport($sender, $world);
        return true;
    }
}
