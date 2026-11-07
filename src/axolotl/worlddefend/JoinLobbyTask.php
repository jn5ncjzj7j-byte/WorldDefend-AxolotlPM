<?php

declare(strict_types=1);

namespace axolotl\worlddefend;

use pocketmine\player\Player;
use pocketmine\scheduler\Task;

final class JoinLobbyTask extends Task{

    public function __construct(
        private Main $plugin,
        private Player $player
    ){}

    public function onRun() : void{
        if(!$this->player->isConnected()){
            return;
        }
        $worldName = (string) $this->plugin->getConfig()->get("lobby-world", "lobby");
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
        if($world === null){
            $this->plugin->getLogger()->warning("Lobby world '" . $worldName . "' is not loaded. Player '" . $this->player->getName() . "' was not teleported.");
            return;
        }
        $this->player->teleport($world->getSafeSpawn());
        $this->plugin->applyWorldMode($this->player);
    }
}
