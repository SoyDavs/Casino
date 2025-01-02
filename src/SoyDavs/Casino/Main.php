<?php

namespace SoyDavs\Casino;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use Vecnavium\FormsUI\SimpleForm;
use Vecnavium\FormsUI\CustomForm;
use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use cooldogedev\BedrockEconomy\libs\cooldogedev\libSQL\exception\SQLException;
use cooldogedev\BedrockEconomy\libs\cooldogedev\libSQL\exception\RecordNotFoundException;
use cooldogedev\BedrockEconomy\exception\InsufficientFundsException;

class Main extends PluginBase {
    private array $blackjackGames;

    public function onEnable(): void {
        $this->saveDefaultConfig(); // Guarda config.yml si no existe
        $this->blackjackGames = [];
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be executed by a player.");
            return true;
        }

        if ($command->getName() === "casino") {
            $this->showMainMenu($sender);
        }

        return true;
    }

    private function showMainMenu(Player $player): void {
        $form = new SimpleForm(function(Player $player, ?int $data) {
            if ($data === null) return;

            switch($data) {
                case 0:
                    $this->showBlackjackMenu($player);
                    break;
                case 1:
                    $this->showCoinFlipMenu($player);
                    break;
                case 2:
                    $this->showNumberGuessMenu($player);
                    break;
            }
        });

        $messages = $this->getConfig()->get("messages");
        $form->setTitle($messages["main_menu_title"]);

        // Añadir botones con imágenes
        $form->addButton($messages["blackjack_button"], 0, "textures/items/diamond");
        $form->addButton($messages["coin_flip_button"], 0, "textures/items/gold_ingot");
        $form->addButton($messages["number_guess_button"], 0, "textures/items/paper");

        $form->sendToPlayer($player);
    }

    private function showBlackjackMenu(Player $player): void {
        $messages = $this->getConfig()->get("messages");

        $form = new CustomForm(function(Player $player, ?array $data) {
            if ($data === null) return;

            $bet = (int) $data[1];
            if ($bet < 1) {
                $player->sendMessage($this->getConfig()->get("messages")["minimum_bet"]);
                return;
            }

            $this->startBlackjack($player, $bet);
        });

        $form->setTitle($messages["blackjack_title"]);
        $form->addLabel($messages["blackjack_description"]);
        $form->addInput($messages["bet_amount_label"], "1000");
        $form->sendToPlayer($player);
    }

    private function startBlackjack(Player $player, int $bet): void {
        BedrockEconomyAPI::CLOSURE()->get(
            xuid: $player->getXuid(),
            username: $player->getName(),
            onSuccess: function(array $result) use ($player, $bet): void {
                if ($result["amount"] < $bet) {
                    $player->sendMessage($this->getConfig()->get("messages")["insufficient_funds"]);
                    return;
                }

                $playerCards = [rand(1, 11), rand(1, 11)];
                $dealerCards = [rand(1, 11)];
                
                $this->blackjackGames[$player->getName()] = [
                    "bet" => $bet,
                    "playerCards" => $playerCards,
                    "dealerCards" => $dealerCards
                ];

                $this->showBlackjackGame($player);
            },
            onError: function(SQLException $exception) use ($player): void {
                if ($exception instanceof RecordNotFoundException) {
                    $player->sendMessage($this->getConfig()->get("messages")["account_not_found"]);
                    return;
                }
                $player->sendMessage($this->getConfig()->get("messages")["error_checking_balance"]);
            }
        );
    }

    private function showBlackjackGame(Player $player): void {
        $game = $this->blackjackGames[$player->getName()];
        
        $form = new SimpleForm(function(Player $player, ?int $data) {
            if ($data === null) return;

            $game = $this->blackjackGames[$player->getName()];
            
            if ($data === 0) { // Hit
                $game["playerCards"][] = rand(1, 11);
                $this->blackjackGames[$player->getName()] = $game;
                
                $total = array_sum($game["playerCards"]);
                if ($total > 21) {
                    $this->endBlackjack($player, false);
                    return;
                }
                
                $this->showBlackjackGame($player);
            } else { // Stand
                $this->dealerPlay($player);
            }
        });

        $playerTotal = array_sum($game["playerCards"]);
        $dealerTotal = array_sum($game["dealerCards"]);

        $form->setTitle("Blackjack - Bet: $" . $game["bet"]);
        $form->setContent(
            "Your cards: " . implode(", ", $game["playerCards"]) . " (Total: $playerTotal)\n" .
            "Dealer cards: " . implode(", ", $game["dealerCards"]) . " (Total: $dealerTotal)"
        );
        $form->addButton("Hit");
        $form->addButton("Stand");
        $form->sendToPlayer($player);
    }

    private function dealerPlay(Player $player): void {
        $game = $this->blackjackGames[$player->getName()];
        
        while (array_sum($game["dealerCards"]) < 17) {
            $game["dealerCards"][] = rand(1, 11);
        }
        
        $playerTotal = array_sum($game["playerCards"]);
        $dealerTotal = array_sum($game["dealerCards"]);
        
        $playerWon = ($dealerTotal > 21) || ($playerTotal > $dealerTotal && $playerTotal <= 21);
        $this->endBlackjack($player, $playerWon);
    }

    private function endBlackjack(Player $player, bool $won): void {
        $game = $this->blackjackGames[$player->getName()];
        $amount = $game["bet"];
        
        if ($won) {
            BedrockEconomyAPI::CLOSURE()->add(
                xuid: $player->getXuid(),
                username: $player->getName(),
                amount: $amount,
                decimals: 0,
                onSuccess: function() use ($player, $amount): void {
                    $player->sendMessage("§aYou won $$amount!");
                },
                onError: function(SQLException $exception) use ($player): void {
                    if ($exception instanceof RecordNotFoundException) {
                        $player->sendMessage("§cAccount not found!");
                        return;
                    }
                    $player->sendMessage("§cError updating balance!");
                }
            );
        } else {
            BedrockEconomyAPI::CLOSURE()->subtract(
                xuid: $player->getXuid(),
                username: $player->getName(),
                amount: $amount,
                decimals: 0,
                onSuccess: function() use ($player, $amount): void {
                    $player->sendMessage("§cYou lost $$amount!");
                },
                onError: function(SQLException $exception) use ($player): void {
                    if ($exception instanceof RecordNotFoundException) {
                        $player->sendMessage("§cAccount not found!");
                        return;
                    }
                    $player->sendMessage("§cError updating balance!");
                }
            );
        }
        
        unset($this->blackjackGames[$player->getName()]);
    }

    private function showCoinFlipMenu(Player $player): void {
        $messages = $this->getConfig()->get("messages");

        $form = new CustomForm(function(Player $player, ?array $data) {
            if ($data === null) return;
            
            $bet = (int) $data[1];
            $choice = $data[2] ? "heads" : "tails";
            
            if ($bet < 1) {
                $player->sendMessage($this->getConfig()->get("messages")["minimum_bet"]);
                return;
            }
            
            $this->playCoinFlip($player, $bet, $choice);
        });

        $form->setTitle($messages["coin_flip_title"]);
        $form->addLabel($messages["coin_flip_description"]);
        $form->addInput($messages["bet_amount_label"], "1000");
        $form->addToggle($messages["coin_flip_heads_label"], true);
        $form->sendToPlayer($player);
    }

    private function playCoinFlip(Player $player, int $bet, string $choice): void {
        BedrockEconomyAPI::CLOSURE()->get(
            xuid: $player->getXuid(),
            username: $player->getName(),
            onSuccess: function(array $result) use ($player, $bet, $choice): void {
                if ($result["amount"] < $bet) {
                    $player->sendMessage($this->getConfig()->get("messages")["insufficient_funds"]);
                    return;
                }

                $flip = rand(0, 1) === 1 ? "heads" : "tails";
                $won = $choice === $flip;
                $amount = $bet;

                if ($won) {
                    BedrockEconomyAPI::CLOSURE()->add(
                        xuid: $player->getXuid(),
                        username: $player->getName(),
                        amount: $amount,
                        decimals: 0,
                        onSuccess: function() use ($player, $amount, $flip): void {
                            $player->sendMessage("§aFlip: $flip - You won $$amount!");
                        },
                        onError: function(SQLException $exception) use ($player): void {
                            if ($exception instanceof RecordNotFoundException) {
                                $player->sendMessage("§cAccount not found!");
                                return;
                            }
                            $player->sendMessage("§cError updating balance!");
                        }
                    );
                } else {
                    BedrockEconomyAPI::CLOSURE()->subtract(
                        xuid: $player->getXuid(),
                        username: $player->getName(),
                        amount: $amount,
                        decimals: 0,
                        onSuccess: function() use ($player, $amount, $flip): void {
                            $player->sendMessage("§cFlip: $flip - You lost $$amount!");
                        },
                        onError: function(SQLException $exception) use ($player): void {
                            if ($exception instanceof RecordNotFoundException) {
                                $player->sendMessage("§cAccount not found!");
                                return;
                            }
                            $player->sendMessage("§cError updating balance!");
                        }
                    );
                }
            },
            onError: function(SQLException $exception) use ($player): void {
                if ($exception instanceof RecordNotFoundException) {
                    $player->sendMessage($this->getConfig()->get("messages")["account_not_found"]);
                    return;
                }
                $player->sendMessage($this->getConfig()->get("messages")["error_checking_balance"]);
            }
        );
    }

    private function showNumberGuessMenu(Player $player): void {
        $messages = $this->getConfig()->get("messages");

        $form = new CustomForm(function(Player $player, ?array $data) {
            if ($data === null) return;

            $bet = (int) $data[1];
            $guess = (int) $data[2];

            if ($bet < 1 || $guess < 1 || $guess > 10) {
                $player->sendMessage($this->getConfig()->get("messages")["minimum_bet"]);
                return;
            }

            $this->playNumberGuess($player, $bet, $guess);
        });

        $form->setTitle($messages["number_guess_title"]);
        $form->addLabel($messages["number_guess_description"]);
        $form->addInput($messages["bet_amount_label"], "1000");
        $form->addInput($messages["number_guess_guess_label"], "5");
        $form->sendToPlayer($player);
    }

    private function playNumberGuess(Player $player, int $bet, int $guess): void {
        BedrockEconomyAPI::CLOSURE()->get(
            xuid: $player->getXuid(),
            username: $player->getName(),
            onSuccess: function(array $result) use ($player, $bet, $guess): void {
                if ($result["amount"] < $bet) {
                    $player->sendMessage($this->getConfig()->get("messages")["insufficient_funds"]);
                    return;
                }

                $number = rand(1, 10);
                $won = $guess === $number;
                $amount = $bet * ($won ? 10 : 1);

                if ($won) {
                    BedrockEconomyAPI::CLOSURE()->add(
                        xuid: $player->getXuid(),
                        username: $player->getName(),
                        amount: $amount,
                        decimals: 0,
                        onSuccess: function() use ($player, $amount, $number): void {
                            $player->sendMessage("§aNumber: $number - You won $$amount!");
                        },
                        onError: function(SQLException $exception) use ($player): void {
                            if ($exception instanceof RecordNotFoundException) {
                                $player->sendMessage("§cAccount not found!");
                                return;
                            }
                            $player->sendMessage("§cError updating balance!");
                        }
                    );
                } else {
                    BedrockEconomyAPI::CLOSURE()->subtract(
                        xuid: $player->getXuid(),
                        username: $player->getName(),
                        amount: $bet,
                        decimals: 0,
                        onSuccess: function() use ($player, $bet, $number): void {
                            $player->sendMessage("§cNumber: $number - You lost $$bet!");
                        },
                        onError: function(SQLException $exception) use ($player): void {
                            if ($exception instanceof RecordNotFoundException) {
                                $player->sendMessage("§cAccount not found!");
                                return;
                            }
                            $player->sendMessage("§cError updating balance!");
                        }
                    );
                }
            },
            onError: function(SQLException $exception) use ($player): void {
                if ($exception instanceof RecordNotFoundException) {
                    $player->sendMessage($this->getConfig()->get("messages")["account_not_found"]);
                    return;
                }
                $player->sendMessage($this->getConfig()->get("messages")["error_checking_balance"]);
            }
        );
    }
}
