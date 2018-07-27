<?php
namespace mysterybox;

/**
 * MysteryBox: Advanced and customisable crates plugin for PMMP
 * CopyRight (C)  2018 CubePM (TheAz928)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use pocketmine\Player;
use pocketmine\IPlayer;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\plugin\PluginBase;

use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;

use pocketmine\tile\Tile;

use mysterybox\tile\MysteryTile;

class Core extends PluginBase{
	
	public const config_ver = 1;
	
	/** @var self */
	private static $instance;
	
	/** @var MysteryBox[] */
	private $boxes = [];
	
	/** @var Config */
	private $key_data;
	
	/** @var EventListener */
	private $event_listener;
	
	/**
	 * @return Core
	 */
	
	public static function getInstance() : Core{
		return self::$instance;
	}
	
	/**
	 * @param void
	 */
	
	public function onLoad(){
		self::$instance = $this;
		
		$this->getLogger()->info(TF::colorize("&7Loading Mystery Boxes from config..."));
		
		$this->saveDefaultConfig();
		
		if($this->getConfig()->get("version", null) !== self::config_ver){
			$this->getLogger()->info(TF::colorize("&cConfig version seems to be incompatible with this version, resetting..."));
			$this->getLogger()->info(TF::colorize("&7If you've a config backup, update your backup with this version before mounting"));
			
			$this->saveResource("config.yml", true);
			$this->getConfig()->reload();
		}
		
		foreach($this->getConfig()->get("boxes", []) as $key => $data){
			try{
				$this->getLogger()->info(TF::colorize("&7Loading Mystery Box: ".($data["name"] ?? "&cUNDEFINED")));
				$box = new MysteryBox($data);
				$this->loadMysteryBox($box);
			}catch(\Exception $e){
                                $this->getLogger()->logException($e);
				$this->getLogger()->info(TF::colorize("&cUnable to load Mystery Box &f#".$key));
			}
		}
	}
	
	/**
	 * @param void
	 */
	
	public function onEnable(){
		$this->event_listener = new EventListener($this);
		Tile::registerTile(MysteryTile::class, ["MysteryTile"]);
		
		$this->getLogger()->info("All configuration seems to be fine, system has been enabled");
	}
	
	/**
	 * @param void
	 */
	
	public function onDisable(){
		$this->getDataBase()->save();
		
	}
	
	/**
	 * @return Config
	 */
	
	public function getDataBase() : Config{
		$path = $this->getDataFolder()."database.yml";
		if($this->key_data == null){
			$this->key_data = new Config($path, Config::YAML);
		}
		
		return $this->key_data;
	}
	
	/**
	 * @param IPlayer $player
	 * @param string $id
	 * @param int $amount
	 *
	 * @return bool
	 */
	
	public function hasMysteryKey(IPlayer $player, string $id, int $amount = 1) : bool{
		return ($this->getDataBase()->get($player->getLowercaseName(), [])[$id] ?? 0) >= $amount;
	}
	
        /**
	 * @param IPlayer $player
	 * @param string $id
	 *
	 * @return int
	 */
	
	public function getMysteryKey(IPlayer $player, string $id) : int{
		return ($this->getDataBase()->get($player->getLowercaseName(), [])[$id] ?? 0);
	}
	
	/**
	 * @param Player $player
	 * @param string $id
	 * @param int $amount
	 */
	
	public function addMysteryKey(IPlayer $player, string $id, int $amount = 1) : void{
		$data = $this->getDataBase()->get(strtolower($player->getName()), []);
		$data[$id] = ($data[$id] ?? 0) + $amount;
		
		$this->getDataBase()->set(strtolower($player->getName()), $data);
	}
	
	/**
	 * @param Player $player
	 * @param string $id
	 * @param int $amount
	 */
	
	public function removeMysteryKey(IPlayer $player, string $id, int $amount = 1) : void{
		$data = $this->getDataBase()->get(strtolower($player->getName()), []);
		$data[$id] = ($data[$id] ?? 0) - $amount;
		
		$this->getDataBase()->set(strtolower($player->getName()), $data);      
	}
	
	/**
	 * @param Player $player
	 * @param string $id
	 * @param int $amount
	 */
	
	public function setMysteryKey(IPlayer $player, string $id, int $amount = 1) : void{
		$data = $this->getDataBase()->get(strtolower($player->getName()), []);
		$data[$id] = $amount;
		
		$this->getDataBase()->set(strtolower($player->getName()), $data);
	}
	
	
	/**
	 * @param MysteryBox $box
	 */
	
	public function loadMysteryBox(MysteryBox $box) : void{
		$this->boxes[$box->getId()] = $box;
	}
	
	/**
	 * @param string $id
	 * @return MysteryBox|null
	 */
	
	public function getMysteryBox(string $id) : ?MysteryBox{
		return $this->boxes[$id] ?? null;
	}
	
	/**
	 * @return MysteryBox[]
	 */
	
	public function getMysteryBoxes() : array{
		return $this->boxes;
	}
	
	/**
	 * @return EventListener
	 */
	
	public function getEventListener() : EventListener{
		return $this->event_listener;
	}
	
	/**
	 * @param string $str
	 * @return Item
	 */
	
	public static function itemFromString(string $str) : Item{
		if(trim($str) == ""){
			return Item::get(Item::AIR);
		}
		
		$i = explode(":", $str);
		
		try{
			$item = Item::fromString($i[0].":".$i[1]);
			$item->setCount((int) $i[2]);
			
			unset($i[0], $i[1], $i[2]);
			
			if(isset($i[3])){
				if(trim($i[3]) !== "" and in_array($i[3], ["default", "none", "d", "~"]) == false){
					$item->setCustomName(str_replace("\n", "\n", TF::colorize($i[3])));
				}
				unset($i[3]);
			}
			
			$i = array_values($i); // No need technically
			
			foreach($i as $k => $d){
				if(($k % 2) == 0){
					if(is_numeric($d)){
						$type = Enchantment::getEnchantment((int) $d);
					}elseif(is_string($d)){
						$type = Enchantment::getEnchantmentByName($d);
					}
				}elseif($k % 2 == 1 and isset($type)){
					$item->addEnchantment(new EnchantmentInstance($type, (int) $d));
					$type = null;
				}
			}
			
			return $item;
		}catch(\Throwable $t){
			self::getInstance()->getLogger()->logException($t);
			self::getInstance()->getLogger()->info("Returned air item (null) to prevent compatibility issues");
			return Item::get(Item::AIR);
		}
	}
	
	/**
	 * @param CommandSender $sender
	 * @param Command $cmd
	 * @param string $label
	 * @param array $args
	 * 
	 * @return bool
	 */
	
	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
		switch($args[0] ?? null){
			default: {
				$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox &e[add | remove | set | create]"));
				break;
			}
			case "add": {
				if(isset($args[1]) == false){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox add &c[name] [box id] [amount]"));
					break;
				}
				if(isset($args[2]) == false){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox add &a[name] &c[box id] [amount]"));
					break;
				}
				if(isset($args[3]) == false or is_numeric($args[3]) == false or $args[3] < 1){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox add &a[name] [box id] &c[amount]"));
					break;
				}
				
				$player = $this->getServer()->getOfflinePlayer($args[1]);
				$id = $args[2];
				$int = (int) $args[3];
				
				$box = $this->getMysteryBox($id);
				
				if($box == null){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7Mystery Box with ID &e{$id}&7 doesn't exist"));
					break;
				}
				
				$this->addMysteryKey($player, $id, $int);
				
				$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7Added &e{$id}&7 key × &b{$int}&7 to &f{$args[1]}'s &7account"));
				break;
			}
			case "remove": {
				if(isset($args[1]) == false){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox remove &c[name] [box id] [amount]"));
					break;
				}
				if(isset($args[2]) == false){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox remove &a[name] &c[box id] [amount]"));
					break;
				}
				if(isset($args[3]) == false or is_numeric($args[3]) == false or $args[3] < 1){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox remove &a[name] [box id] &c[amount]"));
					break;
				}
				
				$player = $this->getServer()->getOfflinePlayer($args[1]);
				$id = $args[2];
				$int = (int) $args[3];
				
				$box = $this->getMysteryBox($id);
				
				if($box == null){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7Mystery Box with ID &e{$id}&7 doesn't exist"));
					break;
				}
				
				if($this->hasMysteryKey($player, $id, $int)){
					$this->removeMysteryKey($player, $id, $int);
				}else{
					$this->setMysteryKey($player, $id, 0);
				}
				
				$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7Removed &e{$id}&7 key × &b{$int}&7 from &f{$args[1]}'s &7account"));
				break;
			}
			case "set": {
				if(isset($args[1]) == false){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox set &c[name] [box id] [amount]"));
					break;
				}
				if(isset($args[2]) == false){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox set &a[name] &c[box id] [amount]"));
					break;
				}
				if(isset($args[3]) == false or is_numeric($args[3]) == false or $args[3] < 1){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox set &a[name] [box id] &c[amount]"));
					break;
				}
				
				$player = $this->getServer()->getOfflinePlayer($args[1]);
				$id = $args[2];
				$int = (int) $args[3];
				
				$box = $this->getMysteryBox($id);
				
				if($box == null){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7Mystery Box with ID &e{$id}&7 doesn't exist"));
					break;
				}
				
				$this->setMysteryKey($player, $id, $int);
				
				$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7Set &e{$id}&7 key × &b{$int}&7 to &f{$args[1]}'s &7account"));
				break;
			}
			case "create": {
				if($sender instanceof Player == false){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox create can only be ran in-game!"));
					break;
				}
				if(isset($args[1]) == false){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7/mysterybox create &c[box id]"));
					break;
				}
				if($this->getMysteryBox($args[1]) == null){
					$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7Invalid Mystery Box ID"));
					break;
				}
				
				$this->getEventListener()->queueBoxCreation($sender, $args[1]);
				
				$sender->sendMessage(TF::colorize($this->getConfig()->get("prefix")." &r&7Tap a block to turn it into a Mystery Box"));
				break;
			}
			case "reload": {
				$this->onDisable();
				$this->onLoad();
				$this->onEnable();
				
				$sender->sendMessage("[MysteryBox] Reload has been completed");
				break;
			}
		}
                    
		return true;
	}
}