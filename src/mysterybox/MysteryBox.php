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

use pocketmine\item\Item;

use pocketmine\nbt\tag\ListTag;

use pocketmine\utils\TextFormat as TF;

use pocketmine\network\mcpe\protocol\BlockEventPacket;

use mysterybox\tile\MysteryTile;

use mysterybox\entity\MysterySkull;

class MysteryBox{
	
	/** @var string */
	protected $name;
	
	/** @var string */
	protected $id;
	
	/** @var array */
	protected $rgb = [0, 0, 0];
	
	/** @var array */
	protected $items = [];
	
	/** @var Item */
	protected $key;
	
	/**
	 * @param array $data
	 *
	 * @throws \Exception on invalid data
	 */
	
	public function __construct(array $data){
		$this->name = TF::colorize($data["name"]);
		$this->id = $data["box.id"];
		
		if(count($data["rgb"]) !== 3){
			throw new \Exception("RGB must contain 3 numbers");
		}
		$this->rgb = $data["rgb"];
		
		if(empty($data["items"])){
			throw new \Exception("Mystery Box must have atleast one item as reward");
		}
		$this->items = $data["items"];

		$key = Item::fromString($data["key"]["item"]);
		$key->setCustomName(TF::colorize($data["key"]["name"] ?? $this->name));
		$key->setLore(array_map(function($i){ return TF::colorize($i); }, $data["key"]["lore"] ?? []));
		
		$this->key = $key;
	}
	
	/**
	 * @return Core
	 */
	
	public function getCore() : Core{
		return Core::getInstance();
	}
	
	/**
	 * @return string
	 */
	
	public function getId() : string{
		return $this->id;
	}
	
	/**
	 * @return string
	 */
	
	public function getName() : string{
		return $this->name;
	}
	
	/**
	 * @return Item
	 */
	
	public function getKey() : Item{
		return clone $this->key;
	}
	
	/**
	 * @param bool $onlyItem
	 *
	 * @return array
	 */
	
	public function getItems(bool $onlyItem = false) : array{
		if($onlyItems){
			$items = [];
			foreach($this->items as $data){
				$items[] = Core::itemFromString($data["item"]);
			}
			
			return $items;
		}
		
		return $this->items;
	}
	
	/**
	 * @return array
	 */
	
	public function getRGB() : array{
		return $this->rgb;
	}

	/**
	 * @param Player $player
	 * @return bool
	 */
	
	public function canOpen(Player $player) : bool{
		return $this->getCore()->hasMysteryKey($player, $this->id) or $player->getInventory()->getItemInHand()->equals($this->key, true, false);
	}
	
	/**
	 * @param Player $player
	 * @param MysteryTile $tile
	 */
	
	public function open(Player $player, MysteryTile $tile) : void{
		$consumed = false;
		$inv = $player->getInventory();
		
		if(($item = $inv->getItemInHand())->equals($this->key, true, false)){
			$item->pop();
			$inv->setItemInHand($item);
			$consumed = true;
		}else{
			if($this->getCore()->hasMysteryKey($player, $this->id)){
				$this->getCore()->removeMysteryKey($player, $this->id, 1);
				$consumed = true;
			}
		}
		
		if($consumed){
			$pk = new BlockEventPacket;
			$pk->x = (int) $tile->x;
			$pk->y = (int) $tile->y;
			$pk->z = (int) $tile->z;
			$pk->eventType = 1;
			$pk->eventData = 1;
			
			$tile->getLevel()->addChunkPacket($tile->x >> 4, $tile->z >> 4, $pk);
			$tile->in_use = true;
			
			$entity = new MysterySkull($player, $tile, [$this, "completeOpenSequence"]);
			$entity->spawnToAll();
		}
	}
	
	/**
	 * @param Player $player
	 * @param MysteryTile $tile
	 */
	
	public function completeOpenSequence(Player $player, MysteryTile $tile) : void{
		if($player->isOnline()){
			$this->grantItem($player, mt_rand(1, 100));
		}
		
		$pk = new BlockEventPacket;
		$pk->x = (int) $tile->x;
		$pk->y = (int) $tile->y;
		$pk->z = (int) $tile->z;
		$pk->eventType = 1;
		$pk->eventData = 0;
			
		$tile->getLevel()->addChunkPacket($tile->x >> 4, $tile->z >> 4, $pk);
		$tile->in_use = false;
	}
	
	/**
	 * @param Player $player
	 * @param null|int $chance
	 */
	
	public function grantItem(Player $player, ?int $chance = null) : void{
		if($chance === null){
			$chance = mt_rand(1, 100);
		}
		
		$done = false;
		$tries = 0;
		
		while($done == false and $tries < 40){
			$tries++;
			$data = $this->items[array_rand($this->items)];
                        
			if($chance <= $data["chance"]){
				$done = true;
				$item = Core::itemFromString($data["item"]);
				break;
			}
			
			if($tries % 10 == 0){
				$chance = mt_rand(1, 100);
			}
		}
		
		if($done){
			$player->addTitle(TF::colorize("&bYou've won"), $item->getCount()." × ".$item->getName(), 20, 10, 40);
			$player->getInventory()->addItem($item);
		}
	}
}