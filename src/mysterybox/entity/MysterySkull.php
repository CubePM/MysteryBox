<?php
namespace mysterybox\entity;

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

use pocketmine\entity\Entity;

use pocketmine\level\particle\LavaParticle;
use pocketmine\level\particle\HugeExplodeSeedParticle;

use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

use mysterybox\tile\MysteryTile;

class MysterySkull extends Entity{
	
	public const NETWORK_ID = Entity::WITHER_SKULL;
	
	/** @var float */
	public $height = 0.4;
	
	/** @var float */
	public $width = 0.4;
	
	/** @var MysteryTile */
	protected $tile;
	
	/** @var callable */
	protected $callable;
	
	/** @var Player */
	protected $player;
        
	/* @var float */
	protected $max_y = 5;
	
	/**
	 * @param Player $player
	 * @param MysteryTile $tile
	 * @param callable $callable
	 */
	
	public function __construct(Player $player, MysteryTile $tile, callable $callable){
		$this->tile = $tile;
		$this->callable = $callable;
		$this->player = $player;
		
		parent::__construct($tile->getLevel(), self::createBaseNBT($tile->asVector3()->add(0.5, 0, 0.5)));
	}
	
	/**
	 * @param void
	 */

	protected function applyGravity() : void{
	
	}
	
	/**
	 * @param int $diff
	 *
	 * @return bool
	 */
	
	public function entityBaseTick(int $diff = 1) : bool{
		$return = parent:: entityBaseTick($diff);
		
		if($this->tile === null or $this->tile->isClosed() or $this->player === null){
			$this->flagForDespawn();
			return true;
		}

		$this->getLevel()->addParticle(new LavaParticle($this->asVector3()));
		       
		if($this->max_y > 0){
			$this->motion->y = 0.05;
			$this->max_y -= 0.1;
		}else{
			if($this->motion->y > 0){
				$this->motion->y = 0;
			}
		}
		
		if($this->yaw > 360){
			$this->yaw = 0;
		}else{
			$this->yaw += 20;
		}
                
		if($this->age >= 20 * 10){
			$callable = $this->callable;
			$callable($this->player, $this->tile);
			
			$this->getLevel()->addParticle(new HugeExplodeSeedParticle($this->asVector3()));
			$this->getLevel()->broadcastLevelSoundEvent($this->asVector3(), LevelSoundEventPacket::SOUND_TWINKLE);
			
			$this->flagForDespawn();
		}

		return $return;
	}
	
	/**
	 * @return bool
	 */
	
	public function canSaveWithChunk() : bool{
		return false;
	}
}