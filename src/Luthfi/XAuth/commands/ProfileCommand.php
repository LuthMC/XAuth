<?php

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use jojoe77777\FormAPI\SimpleForm;

class ProfileCommand extends Command {
    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return false;
        }

        $this->showProfileForm($sender);
        return true;
    }

    private function showProfileForm(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?string $data) {
            if ($data === null) return;

            if ($data === "enable_pin") {
                $this->showPinToggleForm($player);
            }
        });

        $form->setTitle("§bProfile Settings");
        $form->setContent("§eManage your profile settings.");
        $form->addButton("§6Enable PIN", "", "enable_pin");
        $form->sendToPlayer($player);
    }

    private function showPinToggleForm(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?string $data) {
            if ($data === null) return;

            if ($data === "enable") {
                $this->plugin->getPlayerData()->set($player->getName() . ".pin_enabled", true);
                $this->plugin->getPlayerData()->save();
                $player->sendMessage("PIN has been enabled. Please set your PIN using chat (numbers only).");
                $this->plugin->getServer()->getPluginManager()->registerEvents(new class($this->plugin, $player) {
                    private Main $plugin;
                    private Player $player;

                    public function __construct(Main $plugin, Player $player) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                    }

                    public function onChat(\pocketmine\event\player\PlayerChatEvent $event) {
                        if ($event->getPlayer()->getName() !== $this->player->getName()) return;
                        
                        $message = $event->getMessage();
                        
                        if (is_numeric($message)) {
                            $this->plugin->getPlayerData()->set($this->player->getName() . ".pin", $message);
                            $this->plugin->getPlayerData()->save();
                            $this->player->sendMessage("Your PIN has been set to: " . $message);
                            $event->cancel();
                            return;
                        } else {
                            $this->player->sendMessage("Invalid input! Please enter numbers only.");
                            $event->cancel();
                        }
                    }
                });
            } elseif ($data === "disable") {
                $this->plugin->getPlayerData()->set($player->getName() . ".pin_enabled", false);
                $this->plugin->getPlayerData()->save();
                $player->sendMessage("PIN has been disabled.");
            }
        });

        $form->setTitle("§bToggle PIN");
        $form->setContent("§eDo you want to enable or disable your PIN?");
        $form->addButton("§aEnable", "", "enable");
        $form->addButton("§cDisable", "", "disable");
        $form->sendToPlayer($player);
    }
}
