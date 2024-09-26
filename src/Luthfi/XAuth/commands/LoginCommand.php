<?php

namespace Luthfi\XAuth\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use Luthfi\XAuth\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;

class LoginCommand extends Command implements Listener {
    private Main $plugin;

    public function __construct(Main $plugin) {
        parent::__construct("login", "Login to your account", "/login <password>");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return false;
        }

        $name = strtolower($sender->getName());
        if (count($args) !== 1) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("login_usage"));
            return false;
        }

        if (!$this->plugin->getPlayerData()->exists($name)) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("not_registered"));
            return false;
        }

        $password = $args[0];
        $storedPassword = $this->plugin->getPlayerData()->get($name)["password"];

        if ($password !== $storedPassword) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("incorrect_password"));
            return false;
        }

        $sender->addEffect(new EffectInstance(Effect::BLINDNESS(), 100, 1, false));

        $sender->sendMessage($this->plugin->getCustomMessages()->get("login_success"));

        if ($this->plugin->getPlayerData()->get($name)["pin_enabled"]) {
            $this->askForPin($sender);
        } else {
            $sender->removeEffect(Effect::BLINDNESS);
        }

        return true;
    }

    private function askForPin(Player $player): void {
        $player->sendMessage("Please enter your PIN in chat to complete your login.");
        $this->plugin->getServer()->getPluginManager()->registerEvents(new class($this->plugin, $player) {
            private Main $plugin;
            private Player $player;
            private int $attempts = 0;

            public function __construct(Main $plugin, Player $player) {
                $this->plugin = $plugin;
                $this->player = $player;
            }

            public function onChat(PlayerChatEvent $event) {
                if ($event->getPlayer()->getName() !== $this->player->getName()) return;

                $message = $event->getMessage();
                $storedPin = $this->plugin->getPlayerData()->get($this->player->getName())["pin"];

                if (is_numeric($message)) {
                    if ($message === $storedPin) {
                        $this->player->sendMessage("PIN verified successfully! You are now logged in.");
                        $event->cancel();
                        $this->player->removeEffect(Effect::BLINDNESS);
                        return;
                    } else {
                        $this->attempts++;
                        $this->player->sendMessage("Incorrect PIN. Attempts left: " . (3 - $this->attempts));
                        $event->cancel();

                        if ($this->attempts >= 3) {
                            $this->player->kick("You have entered the wrong PIN too many times. You have been kicked.");
                        }
                    }
                } else {
                    $this->player->sendMessage("Invalid input! Please enter numbers only.");
                    $event->cancel();
                }
            }
        });
    }
}
