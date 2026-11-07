<?php

declare(strict_types=1);

namespace axolotl\\worlddefend;

use pocketmine\\entity\\Location;
use pocketmine\\event\\Listener;
use pocketmine\\event\\block\\BlockBreakEvent;
use pocketmine\\event\\block\\BlockPlaceEvent;
use pocketmine\\event\\entity\\EntityTeleportEvent;
use pocketmine\\event\\player\\PlayerJoinEvent;
use pocketmine\\event\\player\\PlayerMoveEvent;
use pocketmine\\event\\player\\PlayerQuitEvent;
use pocketmine\\player\\GameMode;
use pocketmine\\player\\Player;
use pocketmine\\plugin\\PluginBase;
use pocketmine\\world\\Position;

final class Main extends PluginBase implements Listener{

    private array $countdowns = [];
    private array $homes = [];
    private array $worldPositions = [];

    protected function onEnable() : void{
        $this->saveDefaultConfig();
        $this->loadHomes();
        $this->loadWorldPositions();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $map = $this->getServer()->getCommandMap();
        $map->register("worlddefend", new LobbyCommand($this, "lobby", ["hub"]));
        $map->register("worlddefend", new LobbyCommand($this, "play"));
        $map->register("worlddefend", new HomeCommand($this, "sethome"));
        $map->register("worlddefend", new HomeCommand($this, "home"));
        $this->getScheduler()->scheduleRepeatingTask(new PositionSaveTask($this), 100);
    }

    protected function onDisable() : void{
        $this->captureOnlineWorldPositions();
        $this->saveHomes();
        $this->saveWorldPositions();
    }

    public function onJoin(PlayerJoinEvent $event) : void{
        $player = $event->getPlayer();
        $this->applyWorldMode($player);
        if((bool) $this->getConfig()->get("send-players-to-lobby-on-join", true)){
            $this->getScheduler()->scheduleDelayedTask(new JoinLobbyTask($this, $player), 1);
        }
    }

    public function onQuit(PlayerQuitEvent $event) : void{
        $player = $event->getPlayer();
        $this->captureWorldPosition($player);
        $this->saveWorldPositions();
        $this->cancelCountdown($player);
    }

    public function onEntityTeleport(EntityTeleportEvent $event) : void{
        $entity = $event->getEntity();
        if(!$entity instanceof Player){
            return;
        }

        $mainWorld = (string) $this->getConfig()->get("main-world", "world");
        $from = $event->getFrom();
        $to = $event->getTo();
        $playerId = $entity->getUniqueId()->toString();

        if($from->getWorld()->getFolderName() === $mainWorld && $to->getWorld() !== $from->getWorld()){
            $this->storeWorldPosition($entity, $from);
            $this->saveWorldPositions();
            return;
        }

        if($to->getWorld()->getFolderName() === $mainWorld){
            $saved = $this->getSavedWorldPosition($entity);
            if($saved !== null && ($to->distanceSquared($saved) > 2.25)){
                $this->getScheduler()->scheduleDelayedTask(new RestoreWorldPositionTask($this, $entity), 1);
            }
        }
    }

    public function onMove(PlayerMoveEvent $event) : void{
        if($event->getFrom()->getWorld() !== $event->getTo()->getWorld()){
            $this->applyWorldMode($event->getPlayer());
        }
    }

    public function onBreak(BlockBreakEvent $event) : void{
        if($this->isProtected($event->getBlock()->getPosition()->getWorld()) && !$event->getPlayer()->hasPermission("worlddefend.bypass")){
            $event->cancel();
            $event->getPlayer()->sendMessage($this->message("protected"));
        }
    }

    public function onPlace(BlockPlaceEvent $event) : void{
        if($this->isProtected($event->getBlockAgainst()->getPosition()->getWorld()) && !$event->getPlayer()->hasPermission("worlddefend.bypass")){
            $event->cancel();
            $event->getPlayer()->sendMessage($this->message("protected"));
        }
    }

    private function isProtected($world) : bool{
        return in_array($world->getFolderName(), $this->getConfig()->get("protected-worlds", []), true);
    }

    public function applyWorldMode(Player $player) : void{
        $name = $player->getWorld()->getFolderName();
        $mode = strtolower((string) $this->getConfig()->getNested("gamemodes." . $name, ""));
        if($mode === "adventure"){
            $player->setGamemode(GameMode::ADVENTURE());
        }elseif($mode === "survival"){
            $player->setGamemode(GameMode::SURVIVAL());
        }elseif($mode === "creative"){
            $player->setGamemode(GameMode::CREATIVE());
        }
    }

    public function beginTeleport(Player $player, string $worldName) : void{
        $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);
        if($world === null){
            $player->sendMessage($this->message("world-missing"));
            return;
        }

        $target = $world->getSafeSpawn();
        if($worldName === (string) $this->getConfig()->get("main-world", "world")){
            $target = $this->getSavedWorldPosition($player) ?? $target;
        }
        $this->beginPositionTeleport($player, $target);
    }

    public function beginPositionTeleport(Player $player, Position $target) : void{
        $this->cancelCountdown($player);
        $seconds = max(0, (int) $this->getConfig()->get("countdown-seconds", 5));
        if($seconds === 0){
            $this->finishTeleport($player, $target);
            return;
        }
        $task = new CountdownTask($this, $player, $target, $seconds, $player->getWorld(), $player->getPosition());
        $this->countdowns[$player->getUniqueId()->toString()] = $this->getScheduler()->scheduleRepeatingTask($task, 20);
        $player->sendMessage(str_replace("{seconds}", (string) $seconds, $this->message("countdown")));
        $task->start();
    }

    public function cancelCountdown(Player $player) : void{
        $id = $player->getUniqueId()->toString();
        if(isset($this->countdowns[$id])){
            $this->countdowns[$id]->cancel();
            unset($this->countdowns[$id]);
        }
    }

    public function clearCountdown(Player $player) : void{
        unset($this->countdowns[$player->getUniqueId()->toString()]);
    }

    public function finishTeleport(Player $player, Position $target) : void{
        $player->teleport($target);
        $this->applyWorldMode($player);
        $player->sendMessage($this->message("teleported"));
    }

    public function saveHome(Player $player, int $slot) : void{
        $pos = $player->getPosition();
        $this->homes[$player->getUniqueId()->toString()][$slot] = [
            "world" => $player->getWorld()->getFolderName(),
            "x" => $pos->getX(),
            "y" => $pos->getY(),
            "z" => $pos->getZ()
        ];
        $this->saveHomes();
        $player->sendMessage(str_replace("{slot}", (string) $slot, $this->message("home-saved")));
    }

    public function teleportHome(Player $player, int $slot) : void{
        $home = $this->homes[$player->getUniqueId()->toString()][$slot] ?? null;
        if($home === null){
            $player->sendMessage(str_replace("{slot}", (string) $slot, $this->message("home-missing")));
            return;
        }
        $world = $this->getServer()->getWorldManager()->getWorldByName($home["world"]);
        if($world === null){
            $player->sendMessage($this->message("world-missing"));
            return;
        }
        $target = new Position($home["x"], $home["y"], $home["z"], $world);
        $this->beginPositionTeleport($player, $target);
    }

    public function restoreWorldPosition(Player $player) : void{
        $target = $this->getSavedWorldPosition($player);
        if($target !== null && $player->isConnected()){
            $player->teleport($target);
            $this->applyWorldMode($player);
        }
    }

    public function captureOnlineWorldPositions() : void{
        foreach($this->getServer()->getOnlinePlayers() as $player){
            $this->captureWorldPosition($player);
        }
    }

    public function captureWorldPosition(Player $player) : void{
        if($player->getWorld()->getFolderName() === (string) $this->getConfig()->get("main-world", "world")){
            $this->storeWorldPosition($player, $player->getPosition());
        }
    }

    private function storeWorldPosition(Player $player, Position $position) : void{
        $location = $player->getLocation();
        $this->worldPositions[$player->getUniqueId()->toString()] = [
            "x" => $position->getX(),
            "y" => $position->getY(),
            "z" => $position->getZ(),
            "yaw" => $location->getYaw(),
            "pitch" => $location->getPitch()
        ];
    }

    public function getSavedWorldPosition(Player $player) : ?Position{
        $data = $this->worldPositions[$player->getUniqueId()->toString()] ?? null;
        if(!is_array($data)){
            return null;
        }
        $worldName = (string) $this->getConfig()->get("main-world", "world");
        $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);
        if($world === null){
            return null;
        }
        return new Location(
            (float) $data["x"],
            (float) $data["y"],
            (float) $data["z"],
            $world,
            (float) ($data["yaw"] ?? 0.0),
            (float) ($data["pitch"] ?? 0.0)
        );
    }

    private function loadWorldPositions() : void{
        $path = $this->getDataFolder() . "world-positions.json";
        if(is_file($path)){
            $data = json_decode((string) file_get_contents($path), true);
            if(is_array($data)){
                $this->worldPositions = $data;
            }
        }
    }

    public function saveWorldPositions() : void{
        if(!is_dir($this->getDataFolder())){
            @mkdir($this->getDataFolder(), 0777, true);
        }
        file_put_contents($this->getDataFolder() . "world-positions.json", json_encode($this->worldPositions, JSON_PRETTY_PRINT));
    }

    public function message(string $key) : string{
        return (string) $this->getConfig()->getNested("messages." . $key, "");
    }

    private function loadHomes() : void{
        $path = $this->getDataFolder() . "homes.json";
        if(is_file($path)){
            $data = json_decode((string) file_get_contents($path), true);
            if(is_array($data)){
                $this->homes = $data;
            }
        }
    }

    private function saveHomes() : void{
        if(!is_dir($this->getDataFolder())){
            @mkdir($this->getDataFolder(), 0777, true);
        }
        file_put_contents($this->getDataFolder() . "homes.json", json_encode($this->homes, JSON_PRETTY_PRINT));
    }
}
