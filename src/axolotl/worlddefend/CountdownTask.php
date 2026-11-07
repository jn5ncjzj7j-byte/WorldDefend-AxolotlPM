<?php

declare(strict_types=1);

namespace axolotl\\worlddefend;

use pocketmine\\player\\Player;
use pocketmine\\scheduler\\Task;
use pocketmine\\world\\Position;
use pocketmine\\world\\World;

final class CountdownTask extends Task{

    private int $remaining;
    private bool $started = false;

    public function __construct(
        private Main $plugin,
        private Player $player,
        private Position $target,
        int $seconds,
        private World $startWorld,
        private Position $startPosition
    ){
        $this->remaining = $seconds;
    }

    public function start() : void{
        $this->started = true;
    }

    public function onRun() : void{
        if(!$this->started || !$this->player->isConnected()){
            $this->getHandler()?->cancel();
            return;
        }
        if($this->player->getWorld() !== $this->startWorld || $this->player->getPosition()->distanceSquared($this->startPosition) > 0.25){
            $this->plugin->clearCountdown($this->player);
            $this->player->sendMessage($this->plugin->message("cancelled"));
            $this->getHandler()?->cancel();
            return;
        }
        --$this->remaining;
        if($this->remaining > 0){
            $this->player->sendMessage(str_replace("{seconds}", (string) $this->remaining, $this->plugin->message("countdown")));
            return;
        }
        $this->plugin->clearCountdown($this->player);
        $this->getHandler()?->cancel();
        $this->plugin->finishTeleport($this->player, $this->target);
    }
}
