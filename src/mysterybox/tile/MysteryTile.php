<?php
namespace mysterybox\tile;

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

use pocketmine\math\Vector3;

use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\FloatingTextParticle;

use pocketmine\utils\TextFormat as TF;

use pocketmine\tile\Spawnable;

use pocketmine\nbt\tag\CompoundTag;

use mysterybox\Core;

class MysteryTile extends Spawnable{
	
	/** @var string */
	protected $mysteryId;
	
	/** @var array */
	protected $sessions = [];
	
	/** @var bool */
	public $in_use = false;
	
	/** @var array */
	protected $particles = [];
	
	/** @var int */
	protected $p_index = 0;
	
	/**
	 * @return Core
	 */
	
	public function getCore() : Core{
		return Core::getInstance();
	}
	
	/**
	 * @param CompoundTag $nbt
	 */
	
	public function readSaveData(CompoundTag $nbt) : void{
		$this->mysteryId = $nbt->getString("MysteryBoxId");
		
		$this->scheduleUpdate();
                
	}
	
	/**
	 * @param CompoundTag $nbt
	 */
	
	public function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setString("MysteryBoxId", $this->mysteryId);
		
	}
	
	/**
	 * @return string
	 */
	
	public function getMysteryBoxId() : string{
		return $this->mysteryId;
	}
	
	/**
	 * @param CompoundTag $nbt
	 */
	
	public function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$nbt->setString("id", "EnderChest");
            
	}
	
	/**
	 * @param void
	 */
	
	public function close() : void{
		if($this->getLevel() !== null){
			foreach($this->sessions as $id => $ftp){
				$ftp->setInvisible(true);
				
				foreach($ftp->encode() as $pk){
					$this->getLevel()->addChunkPacket($this->x >> 4, $this->z >> 4, $pk);
				}
			}
		}
                
		parent::close();
	}
	
	/**
	 * @param Player $player
	 */
	
	public function updateFloat(Player $player) : void{
		$box = $this->getCore()->getMysteryBox($this->mysteryId);
			
		if($box == null){
			$this->close();
				
			return;
		}
			
		if(isset($this->sessions[$player->getId()])){
			$ftp = $this->sessions[$player->getId()];
			$ftp->setInvisible(true);
				
			foreach($ftp->encode() as $pk){
				$player->dataPacket($pk);
			}
				
			unset($this->sessions[$player->getId()]);
		}
			
		$title = TF::colorize($box->getName()."\n&r&7You have &c{$this->getCore()->getMysteryKey($player, $this->mysteryId)}&7 keys virtually");
		$ftp = new FloatingTextParticle($this->add(0.5, 2, 0.5), $title);
			
		$player->getLevel()->addParticle($ftp, [$player]);
                        
		$this->sessions[$player->getId()] = $ftp;
	}
        
        /**
	 * @return bool
	 */
	
	public function onUpdate() : bool{
		if($this->closed){
			return false;
		}
		
		if($this->getLevel()->getServer()->getTick() % (20 * $this->getCore()->getConfig()->get("text.update", 10)) == 0){
			foreach($this->getLevel()->getPlayers() as $player){
				$this->updateFloat($player);
			}
		}
		
		$s = $this->getParticleParts();
		$s1 = $s[0];
		$s2 = $s[1];
		
		$this->getLevel()->addParticle($s1[$this->p_index]);
		$this->getLevel()->addParticle($s2[$this->p_index]);
		
		if($this->p_index++ >= 360){
			$this->p_index = 0;
		}
		
		return true;
	}
	
	/**
	 * @param bool $recalculate
	 *
	 * @return array
	 */
	
	public function getParticleParts(bool $recalculate = false) : array{
		if(empty($this->particles) or $recalculate){
			$s1 = [];
			$s2 = [];
			
			$box = $this->getCore()->getMysteryBox($this->mysteryId);
			
			if($box == null){
				$this->close();
				
				return [];
			}
			
			$rgb = $box->getRGB();
			
			for($i = 0; $i <= 360; $i++){
				$s1[] = new DustParticle(new Vector3(($this->x + 0.5) + sin($i), $this->y + 0.9, ($this->z + 0.5) + -cos($i)), ...$rgb);
				$s2[] = new DustParticle(new Vector3(($this->x + 0.5) + -sin($i), $this->y + 0.9, ($this->z + 0.5) + cos($i)), ...$rgb);
			}
			
			$this->particles = [$s1, $s2];
		}
		
		return $this->particles;
	}
}