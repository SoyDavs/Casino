# Casino Plugin for PocketMine-MP

A fully-featured casino plugin for PocketMine-MP, allowing players to engage in exciting games like Blackjack, Coin Flip, and Number Guess! The plugin integrates with BedrockEconomy for handling bets and winnings, offering a seamless experience for your Minecraft server.

---

## Features

- **Blackjack**: Challenge the dealer and try to get closer to 21 without going over.
- **Coin Flip**: Bet on heads or tails and test your luck!
- **Number Guess**: Guess a number between 1 and 10 and see if you're lucky.
- **Configurable Messages**: Customize all messages displayed to players via `config.yml`.
- **UI Integration**: Uses `Vecnavium\FormsUI` for smooth and interactive menus.
- **Economy Support**: Fully compatible with BedrockEconomy for handling virtual currency.

---

## Requirements

- **PocketMine-MP**: Latest version.
- **BedrockEconomy**: Economy plugin for PocketMine-MP.
- **Vecnavium\FormsUI**: For user interface forms.

---

## Installation

1. Download the plugin's `.phar` file or compile it from source.
2. Place the `.phar` file into your `plugins/` folder.
3. Restart your server.
4. Ensure that both BedrockEconomy and FormsUI are installed and configured properly.

---

## Configuration

The plugin generates a `config.yml` file on the first run. Here's an example of the default configuration:

```yaml
messages:
  main_menu_title: "Casino"
  blackjack_button: "Blackjack"
  coin_flip_button: "Coin Flip"
  number_guess_button: "Number Guess"
  blackjack_minimum_bet: "Minimum bet is 1!"
  blackjack_win: "You won $$amount!"
  blackjack_lose: "You lost $$amount!"
  coin_flip_win: "Flip: $flip - You won $$amount!"
  coin_flip_lose: "Flip: $flip - You lost $$amount!"
  number_guess_win: "Number was $number - You won $$amount!"
  number_guess_lose: "Number was $number - You lost $$amount!"
  insufficient_funds: "Insufficient funds!"
  account_not_found: "Account not found!"
  config_error: "Configuration error: 'messages' is missing or invalid."
```

You can customize these messages to fit your server's theme.

---

## Usage

### Commands

- `/casino`: Opens the casino main menu.

