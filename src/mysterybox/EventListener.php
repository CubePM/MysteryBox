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

use pocketmine\block\Block;

use pocketmine\tile\Tile;

use pocketmine\event\Listener;

use pocketmine\event\block\BlockBreakEvent;

use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;

use pocketmine\utils\TextFormat as TF;

use mysterybox\tile\MysteryTile;

class EventListener implements Listener{
	
	/**
	 * @return Core
	 */
	
	public function getCore() : Core{
		return Core::getInstance();
	}
	
	/**
	 * @param void
	 */
	
	public function __construct(){
		$this->getCore()->getServer()->getPluginManager()->registerEvents($this, $this->getCore());
	}
	
	/** @var array */
	private $sessions = [];
	
	/**
	 * @param Player $player
	 * @param string $id
	 */
	
	public function queueBoxCreation(Player $player, string $id) : void{
		$this->sessions[$player->getId()] = $id;
	}
	
	/**
	 * @param PlayerInteractEvent $event
	 * @priority MONITOR
	 * @ignoreCancelled false
	 */
	
	public function onInteract(PlayerInteractEvent $event) : void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		
		$tile = $player->getLevel()->getTile($block);
		
		if(($id = $this->sessions[$player->getId()] ?? null) !== null){
			$event->setCancelled();
			$m = [
				0 => 4,
				1 => 2,
				2 => 5,
				3 => 3
			];
			
			$box = Block::get(Block::ENDER_CHEST, $m[$player->getDirection()]);
			
			$player->getLevel()->setBlock($block, $box, true, true);
			
			if($tile instanceof Tile){
				$tile->close();
			}
			
			$nbt = MysteryTile::createNBT($block);
			$nbt->setString("MysteryBoxId", $id);
			
			$box = Tile::createTile("MysteryTile", $player->getLevel(), $nbt);
			
			unset($this->sessions[$player->getId()]);
			return;
		}
		
		if($tile instanceof MysteryTile){
			$event->setCancelled();
			
			if($tile->in_use){
				$player->sendTip(TF::colorize($this->getCore()->getConfig()->get("prefix")." &r&7Mystery Box is already in use!"));
				return;
			}
			
			$box = $this->getCore()->getMysteryBox($tile->getMysteryBoxId());
			
			if($box->canOpen($player)){
				$box->open($player, $tile);
			}else{
				$player->sendTip(TF::colorize($this->getCore()->getConfig()->get("prefix")." &r&7You don't have any keys to open &e{$box->getName()}"));
			}
		}
	}
	
	/**
	 * @param PlayerItemHeldEvent $event
	 * @priority MONITOR
	 * @ignoreCancelled true
	 */
	
	public function onHeld(PlayerItemHeldEvent $event) : void{
		$player = $event->getPlayer();
		$inv = $player->getInventory();
		
		foreach($inv->getContents() as $key => $item){
			foreach($this->getCore()->getMysteryBoxes() as $box){
				if($item->isNull()){
					continue;
				}
				if($box->getKey()->equals($item, true, false) and $item->getName() !== $box->getKey()->getName()){
					$i = $box->getKey();
					$i->setCount($item->getCount());
					$inv->setItem($key, $i);
				}
			}
		}
	}
}