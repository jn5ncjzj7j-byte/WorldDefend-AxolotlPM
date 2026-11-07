# WorldDefend-AxolotlPM

A lightweight PocketMine-MP 5 plugin for lobby protection, automatic per-world game modes, delayed teleporting, and two player home slots.

## Features

- Protect selected worlds from block breaking and placing.
- Set players to Adventure, Survival, or Creative mode by world.
- `/lobby` and `/hub` teleport to the lobby.
- `/play` teleports to the main world.
- `/sethome 1`, `/sethome 2`, `/home 1`, and `/home 2`.
- Configurable teleport countdown. Moving cancels the countdown.
- Home locations persist across restarts.

## Default setup

The default config expects:
- `lobby`: Adventure mode and protected building.
- `world`: Survival mode and normal building.

World names must match the loaded world folder names.

## Install

1. Open the repository's **Actions** tab.
2. Open the latest successful **Build plugin** workflow run.
3. Download the `WorldDefendAxolotl` artifact.
4. Put `WorldDefendAxolotl.phar` in the server's `plugins/` folder.
5. Start the server once, edit `plugin_data/WorldDefendAxolotl/config.yml` if needed, then restart.

## Commands

| Command | Description |
|---|---|
| `/lobby`, `/hub` | Teleport to lobby |
| `/play` | Teleport to main world |
| `/sethome 1` or `/sethome 2` | Save a home |
| `/home 1` or `/home 2` | Teleport to a saved home |

## Configuration

See `resources/config.yml`. Change `lobby-world`, `main-world`, `protected-worlds`, `gamemodes`, and `countdown-seconds` to match your server.

## Compatibility

The source targets PocketMine-MP API 5. Axolotl-PM is a modified PocketMine fork, so runtime compatibility must be confirmed on a backup server. The automated workflow checks PHP syntax and packages a PHAR; a successful build is not proof that the plugin has been tested on Axolotl-PM 5.51.1.
