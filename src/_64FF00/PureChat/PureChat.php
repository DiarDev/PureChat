<?php

namespace _64FF00\PureChat;

use _64FF00\PureChat\factions\BedrockClans;
use _64FF00\PureChat\factions\FactionMaster;
use _64FF00\PureChat\factions\FactionsInterface;
use _64FF00\PureChat\factions\PiggyFactions;
use _64FF00\PureChat\factions\SimpleFaction;
use _64FF00\PurePerms\PPGroup;
use _64FF00\PurePerms\PurePerms;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class PureChat extends PluginBase
{
    const MAIN_PREFIX = "§l§f[§aACM§f] »§r§7 ";

    private Config $config;
    private ?FactionsInterface $factionsAPI;
    private PurePerms $purePerms;

    # CheckConfig by fernanACM
    private const CONFIG_VERSION = "3.0.0-ACM";

    private $tags = [];

    public function onLoad(): void{
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        /*if (!$this->config->get("version")) {
            $version = $this->getDescription()->getVersion();
            $this->config->set("version", $version);
            $this->fixOldConfig();
        }*/
        $this->loadCheck();
        $purePerms = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        assert($purePerms instanceof PurePerms);
        $this->purePerms = $purePerms;
    }

    public function onEnable(): void{
        $this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            public function __construct(
                private $plugin
                ){}

            public function onRun(): void {
              foreach(\pocketmine\Server::getInstance()->getOnlinePlayers() as $player) {
                $plugin->tags[$player->getName()] = [
                    "{tag}" => \Di4rDev\tags\Loader::getInstance()->getTagOwner($player),
                    "{clan}" => \Noob\Clan::getClan($player)
                    ];
           },20);
        }
        $this->getLogger()->info("
  ____                           ____   _               _   
 |  _ \   _   _   _ __    ___   / ___| | |__     __ _  | |_ 
 | |_) | | | | | | '__|  / _ \ | |     | '_ \   / _` | | __|
 |  __/  | |_| | | |    |  __/ | |___  | | | | | (_| | | |_ 
 |_|      \__,_| |_|     \___|  \____| |_| |_|  \__,_|  \__|
        by fernanACM and _64FF00 - https://github.com/fernanACM
                                                             ");
        $this->loadFactionsPlugin();
        $this->getServer()->getPluginManager()->registerEvents(new PCListener($this), $this);
    }

    /**
     * @return void
     */
    private function loadCheck(): void{
        if((!$this->config->exists("config-version")) || ($this->config->get("config-version") != self::CONFIG_VERSION)){
            rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config_old.yml");
            $this->saveResource("config.yml");
            $this->getLogger()->critical("Your configuration file is outdated.");
            $this->getLogger()->notice("Your old configuration has been saved as config_old.yml and a new configuration file has been generated. Please update accordingly.");
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch (strtolower($command->getName())) {
            case "setformat":

                if (count($args) < 3) {
                    $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " Usage: /setformat <group> <world> <format>");
                    return true;
                }

                $group = $this->purePerms->getGroup($args[0]);

                if ($group === null) {
                    $sender->sendMessage(TextFormat::RED . self::MAIN_PREFIX . " Group " . $args[0] . "does NOT exist.");
                    return true;
                }
                $WorldName = null;
                if ($args[1] !== "null" and $args[1] !== "global") {
                    $World = $this->getServer()->getWorldManager()->getWorldByName($args[1]);
                    if ($World === null) {
                        $sender->sendMessage(TextFormat::RED . self::MAIN_PREFIX . " Invalid World Name!");
                        return true;
                    }

                    $WorldName = $World->getDisplayName();
                }

                $chatFormat = implode(" ", array_slice($args, 2));
                $this->setOriginalChatFormat($group, $chatFormat, $WorldName);
                $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " You set the chat format of the group to " . $chatFormat . ".");
                break;

            case "setnametag":

                if (count($args) < 3) {
                    $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " Usage: /setnametag <group> <world> <format>");
                    return true;
                }

                $group = $this->purePerms->getGroup($args[0]);

                if ($group === null) {
                    $sender->sendMessage(TextFormat::RED . self::MAIN_PREFIX . " Group " . $args[0] . "does NOT exist.");
                    return true;
                }

                $WorldName = null;

                if ($args[1] !== "null" and $args[1] !== "global") {
                    $World = $this->getServer()->getWorldManager()->getWorldByName($args[1]);

                    if ($World === null) {
                        $sender->sendMessage(TextFormat::RED . self::MAIN_PREFIX . " Invalid World Name!");

                        return true;
                    }

                    $WorldName = $World->getDisplayName();
                }

                $nameTag = implode(" ", array_slice($args, 2));

                $this->setOriginalNametag($group, $nameTag, $WorldName);

                $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " You set the nametag of the group to " . $nameTag . ".");

                break;


            case "setprefix":

                if (!$sender instanceof Player) {
                    $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " This command can be only used in-game.");
                    return true;
                }

                if (!isset($args[0])) {
                    $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " Usage: /setprefix <prefix>");
                    return true;
                }

                $WorldName = $this->config->get("enable-multiworld-chat") ? $sender->getWorld()->getDisplayName() : null;
                $prefix = str_replace("{BLANK}", ' ', implode('', $args));
                $this->setPrefix($prefix, $sender, $WorldName);
                $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " You set your prefix to " . $prefix . ".");
                break;

            case "setsuffix":

                if (!$sender instanceof Player) {
                    $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " This command can be only used in-game.");
                    return true;
                }

                if (!isset($args[0])) {
                    $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " Usage: /setsuffix <suffix>");
                    return true;
                }

                $WorldName = $this->config->get("enable-multiworld-chat") ? $sender->getWorld()->getDisplayName() : null;
                $suffix = str_replace("{BLANK}", ' ', implode('', $args));
                $this->setSuffix($suffix, $sender, $WorldName);
                $sender->sendMessage(TextFormat::GREEN . self::MAIN_PREFIX . " You set your suffix to " . $suffix . ".");
                break;
        }

        return true;
    }

    private function fixOldConfig(){
        $tempData = $this->config->getAll();
        $version = $this->getDescription()->getVersion();
        $tempData["version"] = $version;
        /*if ($this->getConfig()->get("faction-support", true) === true) {            
           if (!isset($tempData["default-factions-plugin"]))
               $tempData["default-factions-plugin"] = null;
        }*/
        if (isset($tempData["enable-multiworld-support"])) {
            $tempData["enable-multiworld-chat"] = $tempData["enable-multiworld-support"];
            unset($tempData["enable-multiworld-support"]);
        }

        if (isset($tempData["custom-no-fac-message"])) unset($tempData["custom-no-fac-message"]);

        if (isset($tempData["groups"])) {
            foreach ($tempData["groups"] as $groupName => $tempGroupData) {
                if (isset($tempGroupData["default-chat"])) {
                    $tempGroupData["chat"] = $this->fixOldData($tempGroupData["default-chat"]);
                    unset($tempGroupData["default-chat"]);
                }

                if (isset($tempGroupData["default-nametag"])) {
                    $tempGroupData["nametag"] = $this->fixOldData($tempGroupData["default-nametag"]);
                    unset($tempGroupData["default-nametag"]);
                }

                if (isset($tempGroupData["worlds"])) {
                    foreach ($tempGroupData["worlds"] as $worldName => $worldData) {
                        if (isset($worldData["default-chat"])) {
                            $worldData["chat"] = $this->fixOldData($worldData["default-chat"]);
                            unset($worldData["default-chat"]);
                        }

                        if (isset($worldData["default-nametag"])) {
                            $worldData["nametag"] = $this->fixOldData($worldData["default-nametag"]);
                            unset($worldData["default-nametag"]);
                        }

                        $tempGroupData["worlds"][$worldName] = $worldData;
                    }
                }
                $tempData["groups"][$groupName] = $tempGroupData;
            }
        }
        $this->config->setAll($tempData);
        $this->config->save();
        $this->config->reload();
        $this->getLogger()->notice("Upgraded PureChat config.yml to the latest version");
    }

    private function fixOldData(string $string): string
    {
        $string = str_replace("{COLOR_BLACK}", "&0", $string);
        $string = str_replace("{COLOR_DARK_BLUE}", "&1", $string);
        $string = str_replace("{COLOR_DARK_GREEN}", "&2", $string);
        $string = str_replace("{COLOR_DARK_AQUA}", "&3", $string);
        $string = str_replace("{COLOR_DARK_RED}", "&4", $string);
        $string = str_replace("{COLOR_DARK_PURPLE}", "&5", $string);
        $string = str_replace("{COLOR_GOLD}", "&6", $string);
        $string = str_replace("{COLOR_GRAY}", "&7", $string);
        $string = str_replace("{COLOR_DARK_GRAY}", "&8", $string);
        $string = str_replace("{COLOR_BLUE}", "&9", $string);
        $string = str_replace("{COLOR_GREEN}", "&a", $string);
        $string = str_replace("{COLOR_AQUA}", "&b", $string);
        $string = str_replace("{COLOR_RED}", "&c", $string);
        $string = str_replace("{COLOR_LIGHT_PURPLE}", "&d", $string);
        $string = str_replace("{COLOR_YELLOW}", "&e", $string);
        $string = str_replace("{COLOR_WHITE}", "&f", $string);

        $string = str_replace("{FORMAT_OBFUSCATED}", "&k", $string);
        $string = str_replace("{FORMAT_BOLD}", "&l", $string);
        $string = str_replace("{FORMAT_STRIKETHROUGH}", "&m", $string);
        $string = str_replace("{FORMAT_UNDERLINE}", "&n", $string);
        $string = str_replace("{FORMAT_ITALIC}", "&o", $string);
        $string = str_replace("{FORMAT_RESET}", "&r", $string);

        $string = str_replace("{world_name}", "{world}", $string);
        $string = str_replace("{faction}", "{fac_rank}{fac_name}", $string);
        $string = str_replace("{user_name}", "{display_name}", $string);
        $string = str_replace("{message}", "{msg}", $string);

        return $string;
    }

    private function loadFactionsPlugin(){
        $factionsPluginName = $this->config->get("default-factions-plugin");

        if($factionsPluginName === null){
            $this->getLogger()->notice("No valid factions plugin in default-factions-plugin node was found. Disabling factions plugin support.");
        }else{
            switch(strtolower($factionsPluginName)){
                case "piggyfactions":
                    if($this->getServer()->getPluginManager()->getPlugin("PiggyFactions") !== null){
                        $this->factionsAPI = new PiggyFactions();
                        $this->getLogger()->notice("PiggyFactions support enabled.");
                        break;
                    }
                    $this->getLogger()->notice("PiggyFactions was not found. Disabling factions plugin support.");
                    break;
                case "simplefaction":
                    if($this->getServer()->getPluginManager()->getPlugin("SimpleFaction") !== null){
                        $this->factionsAPI = new SimpleFaction();
                        $this->getLogger()->notice("SimpleFaction support enabled.");
                        break;
                    }
                    $this->getLogger()->notice("SimpleFaction was not found. Disabling factions plugin support.");
                break;

                case "factionmaster":
                    if($this->getServer()->getPluginManager()->getPlugin("FactionMaster") !== null){
                        $this->factionsAPI = new FactionMaster();
                        $this->getLogger()->notice("FactionMaster support enabled.");
                        break;
                    }
                    $this->getLogger()->notice("FactionMaster was not found. Disabling factions plugin support.");
                break;

                case "bedrockclans":
                    if($this->getServer()->getPluginManager()->getPlugin("BedrockClans") !== null){
                        $this->factionsAPI = new BedrockClans();
                        $this->getLogger()->notice("BedrockClans support enabled.");
                        break;
                    }
                    $this->getLogger()->notice("BedrockClans was not found. Disabling factions plugin support.");
                break;
                default:
                    $this->getLogger()->notice("No valid factions plugin in default-factions-plugin node was found. Disabling factions plugin support.");
                    break;
            }
        }
    }

    /*
          888  888          d8888 8888888b. 8888888
          888  888         d88888 888   Y88b  888
        888888888888      d88P888 888    888  888
          888  888       d88P 888 888   d88P  888
          888  888      d88P  888 8888888P"   888
        888888888888   d88P   888 888         888
          888  888    d8888888888 888         888
          888  888   d88P     888 888       8888888
    */

    public function applyColors(string $string): string
    {
        return TextFormat::colorize($string);
    }

    public function applyPCTags(string $string, Player $player, ?string $message, ?string $WorldName): string
    {
        // TODO
        $string = str_replace("{display_name}", $player->getDisplayName(), $string);
        if ($message === null)
            $message = "";
        if ($player->hasPermission("pchat.coloredMessages")) {
            $string = str_replace("{msg}", $this->applyColors($message), $string);
        } else {
            $string = str_replace("{msg}", $this->stripColors($message), $string);
        }
        if ($this->getConfig()->get("faction-support", false) === true) {
            if ($this->factionsAPI) {
               $string = str_replace("{fac_name}", $this->factionsAPI->getPlayerFaction($player), $string);
               $string = str_replace("{fac_rank}", $this->factionsAPI->getPlayerRank($player), $string);
            } else {
               $string = str_replace("{fac_name}", '', $string);
               $string = str_replace("{fac_rank}", '', $string);
            }
        }
        $string = str_replace("{world}", ($WorldName === null ? "" : $WorldName), $string);
        $string = str_replace("{prefix}", $this->getPrefix($player, $WorldName), $string);
        $string = str_replace("{suffix}", $this->getSuffix($player, $WorldName), $string);
        foreach($this->tags[$player->getName()] as $tag => $replace) {
          $string = str_replace($tag, $replace, $string);
        }
        return $string;
    }

    public function getChatFormat(Player $player, ?string $message, ?string $WorldName = null): string
    {
        $originalChatFormat = $this->getOriginalChatFormat($player, $WorldName);
        $chatFormat = $this->applyColors($originalChatFormat);
        $chatFormat = $this->applyPCTags($chatFormat, $player, $message, $WorldName);
        return $chatFormat;
    }

    public function getNametag(Player $player, ?string $WorldName = null): string
    {
        $originalNametag = $this->getOriginalNametag($player, $WorldName);
        $nameTag = $this->applyColors($originalNametag);
        $nameTag = $this->applyPCTags($nameTag, $player, null, $WorldName);
        return $nameTag;
    }

    public function getOriginalChatFormat(Player $player, ?string $WorldName = null): string
    {
        /** @var PPGroup $group */
        $group = $this->purePerms->getUserDataMgr()->getGroup($player, $WorldName);
        if ($WorldName === null) {
            $originalChatFormat = $this->config->getNested("groups." . $group->getName() . ".chat");
            if (!is_string($originalChatFormat)) {
                $this->getLogger()->critical("Invalid chat format found in config.yml (Group: " . $group->getName() . ") / Setting it to default value.");
                $this->config->setNested("groups." . $group->getName() . ".chat", $originalChatFormat = "&8&l[" . $group->getName() . "]&f&r {display_name} &7> {msg}");
                $this->config->save();
                $this->config->reload();
            }

            return $originalChatFormat;
        } else {
            $originalChatFormat = $this->config->getNested("groups." . $group->getName() . "worlds.$WorldName.chat");
            if (!is_string($originalChatFormat)) {
                $this->getLogger()->critical("Invalid chat format found in config.yml (Group: " . $group->getName() . ", WorldName = $WorldName) / Setting it to default value.");
                $this->config->setNested("groups." . $group->getName() . "worlds.$WorldName.chat", $originalChatFormat = "&8&l[" . $group->getName() . "]&f&r {display_name} &7> {msg}");
                $this->config->save();
                $this->config->reload();
            }

            return $originalChatFormat;
        }
    }

    public function getOriginalNametag(Player $player, ?string $WorldName = null): string
    {
        /** @var PPGroup $group */
        $group = $this->purePerms->getUserDataMgr()->getGroup($player, $WorldName);
        if ($WorldName === null) {
            $originalNametag = $this->config->getNested("groups." . $group->getName() . ".nametag");
            if (!is_string($originalNametag)) {
                $this->getLogger()->critical("Invalid nametag found in config.yml (Group: " . $group->getName() . ") / Setting it to default value.");
                $this->config->setNested("groups." . $group->getName() . ".nametag", $originalNametag = "&8&l[" . $group->getName() . "]&f&r {display_name}");
                $this->config->save();
                $this->config->reload();
            }
            return $originalNametag;
        } else {
            $originalNametag = $this->config->getNested("groups." . $group->getName() . "worlds.$WorldName.nametag");
            if (!is_string(($originalNametag))) {
                $this->getLogger()->critical("Invalid nametag found in config.yml (Group: " . $group->getName() . ", WorldName = $WorldName) / Setting it to default value.");
                $this->config->setNested("groups." . $group->getName() . "worlds.$WorldName.nametag", $originalNametag = "&8&l[" . $group->getName() . "]&f&r {display_name}");
                $this->config->save();
                $this->config->reload();
            }
            return $originalNametag;
        }
    }

    public function getPrefix(Player $player, ?string $WorldName = null): string
    {
        if ($WorldName === null) {
            $prefix = $this->purePerms->getUserDataMgr()->getNode($player, "prefix");
            return is_string($prefix) ? $prefix : '';
        } else {
            $worldData = $this->purePerms->getUserDataMgr()->getWorldData($player, $WorldName);
            if (!isset($worldData["prefix"]) || !is_string($worldData["prefix"]))
                return "";
            return $worldData["prefix"];
        }
    }

    public function getSuffix(Player $player, ?string $WorldName = null): string
    {
        if ($WorldName === null) {
            $suffix = $this->purePerms->getUserDataMgr()->getNode($player, "suffix");
            return is_string($suffix) ? $suffix : '';
        } else {
            $worldData = $this->purePerms->getUserDataMgr()->getWorldData($player, $WorldName);

            if (!isset($worldData["suffix"]) || !is_string($worldData["suffix"]))
                return "";
            return $worldData["suffix"];
        }
    }

    public function setOriginalChatFormat(PPGroup $group, string $chatFormat, ?string $WorldName = null): bool
    {
        if ($WorldName === null) {
            $this->config->setNested("groups." . $group->getName() . ".chat", $chatFormat);
        } else {
            $this->config->setNested("groups." . $group->getName() . "worlds.$WorldName.chat", $chatFormat);
        }
        $this->config->save();
        $this->config->reload();
        return true;
    }

    public function setOriginalNametag(PPGroup $group, string $nameTag, ?string $WorldName = null): bool
    {
        if ($WorldName === null) {
            $this->config->setNested("groups." . $group->getName() . ".nametag", $nameTag);
        } else {
            $this->config->setNested("groups." . $group->getName() . "worlds.$WorldName.nametag", $nameTag);
        }
        $this->config->save();
        $this->config->reload();
        return true;
    }

    public function setPrefix(string $prefix, Player $player, ?string $WorldName = null): bool
    {
        if ($WorldName === null) {
            $this->purePerms->getUserDataMgr()->setNode($player, "prefix", $prefix);
        } else {
            $worldData = $this->purePerms->getUserDataMgr()->getWorldData($player, $WorldName);
            $worldData["prefix"] = $prefix;
            $this->purePerms->getUserDataMgr()->setWorldData($player, $WorldName, $worldData);
        }

        return true;
    }

    public function setSuffix(string $suffix, Player $player, ?string $WorldName = null): bool
    {
        if ($WorldName === null) {
            $this->purePerms->getUserDataMgr()->setNode($player, "suffix", $suffix);
        } else {
            $worldData = $this->purePerms->getUserDataMgr()->getWorldData($player, $WorldName);
            $worldData["suffix"] = $suffix;
            $this->purePerms->getUserDataMgr()->setWorldData($player, $WorldName, $worldData);
        }

        return true;
    }

    public function stripColors(string $string): string
    {
        return TextFormat::clean($string);
    }
}
