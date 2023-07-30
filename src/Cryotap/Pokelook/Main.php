<?php

namespace Cryotap\Pokelook;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\http\HTTPException;
use pocketmine\utils\TextFormat as TF;
use jojoe77777\FormAPI\FormAPI;
use Vecnavium\FormsUI\SimpleForm;
use Vecnavium\FormsUI\CustomForm;

class Main extends PluginBase {

    public function onEnable(): void {
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "pokelook") {
            if ($sender instanceof Player) {
                if ($sender->hasPermission("pokel.use")) {
                    $this->openPokemonSearchForm($sender);
					return true;
                } else {
                    $sender->sendMessage(TF::RED . "You don't have permission to use this command.");
                }
            } else {
                $sender->sendMessage(TF::RED . "Please run this command in-game.");
				return false;
            }
        }
    }

    private function openPokemonSearchForm(Player $player): void {
    $form = new CustomForm(function (Player $player, $data = null) {
        if ($data !== null) {
            $pokemonName = strtolower(trim($data[0]));
            $pokeApiUrl = "https://pokeapi.co/api/v2/pokemon/" . urlencode($pokemonName);

            $response = @file_get_contents($pokeApiUrl);
            if ($response === false) {
                $player->sendMessage(TF::RED . "Error: Unable to fetch Pokémon data.");
                return;
            }

            $httpCode = $http_response_header[0];
            if (strpos($httpCode, "200") !== false) {
                $pokemonData = json_decode($response, true);

                if (!$pokemonData) {
                    $player->sendMessage(TF::RED . "Error: Pokémon not found.");
                } else {
                    $this->sendPokemonInformation($player, $pokemonData);
                }
            } elseif (strpos($httpCode, "404") !== false) {
                $player->sendMessage(TF::RED . "Error: Pokémon not found.");
            } else {
                $player->sendMessage(TF::RED . "Error: Unable to fetch Pokémon data. Please try again later.");
            }
        }
    });

    $form->setTitle(TF::DARK_PURPLE . "Pokémon Lookup");
    $form->addInput(TF::AQUA . "Enter Pokémon name:", "Bulbasaur");
    $player->sendForm($form);
}

    private function sendPokemonInformation(Player $player, array $pokemonData): void {
        $name = $pokemonData['name'];
        $types = implode(", ", array_map(function ($type) {
            return $type['type']['name'];
        }, $pokemonData['types']));
        $abilities = implode(", ", array_map(function ($ability) {
            return $ability['ability']['name'];
        }, $pokemonData['abilities']));

        $evolutionDataUrl = $pokemonData['species']['url'];
        $evolutionData = json_decode(file_get_contents($evolutionDataUrl), true);
        $evolutionChain = $this->extractEvolutionChain($evolutionData);

        $locationData = $this->getPokemonLocationData($pokemonData['id']);

        $form = new customForm(function (Player $player, $data): void {
            // No need to handle anything here, as we only want to display information.
        });
        $form->setTitle(TF::DARK_PURPLE . "Pokémon Information");
        $form->addLabel(TF::DARK_RED . "Here is the information for Pokémon: " . $name);
        $form->addLabel(TF::DARK_RED ."Name: " . TF::RED . $name);
        $form->addLabel(TF::DARK_RED ."Type(s): " . TF::RED . $types);
        $form->addLabel(TF::DARK_RED ."Abilities: " . TF::RED . $abilities);
        $form->addLabel(TF::DARK_RED ."Evolution Chain: " . TF::RED . $evolutionChain);
        $form->addLabel(TF::DARK_RED ."Location(s): " . TF::RED . implode(", ", $locationData));

        $player->sendForm($form);
    }

    private function extractEvolutionChain(array $evolutionData): string {
        if (!isset($evolutionData['evolution_chain']['url'])) {
            return "Evolution chain data not found.";
        }

        $evolutionChainUrl = $evolutionData['evolution_chain']['url'];
        $evolutionChainData = json_decode(file_get_contents($evolutionChainUrl), true);

        $evolutionChain = [];
        $currentStep = $evolutionChainData['chain'];

        while (isset($currentStep['evolves_to']) && !empty($currentStep['evolves_to'])) {
            $evolutionDetails = $currentStep['evolves_to'][0]['evolution_details'][0];
            $trigger = $evolutionDetails['trigger']['name'];

            if ($trigger === 'level-up') {
                $minLevel = $evolutionDetails['min_level'] ?? null;
                $evolutionChain[] = "Level " . $minLevel;
            } elseif ($trigger === 'use-item') {
                $item = $evolutionDetails['item']['name'] ?? 'unknown item';
                $evolutionChain[] = "Use " . ucfirst($item);
            } elseif ($trigger === 'trade') {
                $evolutionChain[] = "Trade";
            } else {
                $evolutionChain[] = "Unknown evolution trigger";
            }

            $currentStep = $currentStep['evolves_to'][0];
        }

        return implode(" -> ", $evolutionChain);
    }

    private function getPokemonLocationData(int $pokemonId): array {
        $pokeApiUrl = "https://pokeapi.co/api/v2/pokemon/" . $pokemonId . "/encounters";
        $locationData = json_decode(file_get_contents($pokeApiUrl), true);

        $locations = [];

        foreach ($locationData as $data) {
            $locationName = $data['location_area']['name'];
            if (!in_array($locationName, $locations)) {
                $locations[] = $locationName;
            }
        }

        return $locations;
    }
}