<?php

declare(strict_types=1);

namespace axolotl\\worlddefend;

use pocketmine\\scheduler\\Task;

final class PositionSaveTask extends Task{

    public function __construct(private Main $plugin){}

    public function onRun() : void{
        $this->plugin->captureOnlineWorldPositions();
        $this->plugin->saveWorldPositions();
    }
}
