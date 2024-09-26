<?php

namespace Luthfi\XAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use Luthfi\XAuth\commands\RegisterCommand;
use Luthfi\XAuth\commands\LoginCommand;
use Luthfi\XAuth\commands\ResetPasswordCommand;
use Luthfi\XAuth\commands\ProfileCommand;

class Main extends PluginBase implements Listener {

    private Config $playerData;
    private Config $configData;
    private Config $languageMessages;

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->playerData = new Config($this->getDataFolder() . "players.yml", Config::YAML);
        $this->saveDefaultConfig();
        $this->configData = $this->getConfig();
        $language = $this->configData->get("language", "en");
        $this->languageMessages = new Config($this->getDataFolder() . "lang/" . $language . ".yml", Config::YAML);
        $this->checkConfigVersion();
        $this->getServer()->getCommandMap()->register("register", new RegisterCommand($this));
        $this->getServer()->getCommandMap()->register("login", new LoginCommand($this));
        $this->getServer()->getCommandMap()->register("resetpassword", new ResetPasswordCommand($this));
        $this->getServer()->getCommandMap()->register("profile", new ProfileCommand($this));
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $name = strtolower($player->getName());

        if ($this->playerData->exists($name)) {
            $ip = $this->playerData->get($name)["ip"];
            $currentIp = $player->getNetworkSession()->getIp();

            if ($this->configData->get("auto-login") && $ip === $currentIp) {
                $player->sendMessage($this->languageMessages->get("messages")["login_success"]);
                $this->sendTitleMessage($player, "login_success");
            } else {
                $player->sendMessage($this->languageMessages->get("messages")["login_prompt"]);
                $this->sendTitleMessage($player, "login_prompt");
            }
        } else {
            $player->sendMessage($this->languageMessages->get("messages")["register_prompt"]);
            $this->sendTitleMessage($player, "register_prompt");
        }
    }

    private function checkConfigVersion(): void {
        $currentVersion = $this->configData->get("config-version", 1.0);
        if ($currentVersion < 1.0) {
            $this->getLogger()->warning("Your config.yml is outdated! Please update it to the latest version.");
        }
    }

    private function sendTitleMessage(Player $player, string $messageKey): void {
        if ($this->configData->get("enable_titles")) {
            $titleConfig = $this->languageMessages->get("titles")[$messageKey];
            $title = $titleConfig["title"];
            $subtitle = $titleConfig["subtitle"];
            $interval = $titleConfig["interval"] * 20;

            $this->getScheduler()->scheduleRepeatingTask(new class($player, $title, $subtitle) extends \pocketmine\scheduler\Task {
                private Player $player;
                private string $title;
                private string $subtitle;

                public function __construct(Player $player, string $title, string $subtitle) {
                    $this->player = $player;
                    $this->title = $title;
                    $this->subtitle = $subtitle;
                }

                public function onRun(): void {
                    if ($this->player->isOnline()) {
                        $this->player->sendTitle($this->title, $this->subtitle);
                    }
                }
            }, $interval);
        }
    }

    public function getPlayerData(): Config {
        return $this->playerData;
    }

    public function getCustomMessages(): Config {
        return $this->languageMessages;
    }
}
