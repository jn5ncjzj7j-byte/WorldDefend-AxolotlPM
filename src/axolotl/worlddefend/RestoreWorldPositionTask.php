<?php

declare(strict_types=1);

namespace axolotl\worlddefend;

use pocketmine\player\Player;
use pocketmine\scheduler\Task;

final class RestoreWorldPositionTask extends Task{

    public function __construct(
        private Main $plugin,
        private Player $player
    ){}

    public function onRun() : void{
        if($this->player->isConnected()){
            $this->plugin->restoreWorldPosition($this->player);
        }
    }
}
