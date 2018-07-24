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

use pocketmine\math\Vector3;

use pocketmine\level\particle\LavaParticle;
use pocketmine\level\particle\HugeExplodeSeedParticle;
use pocketmine\level\particle\FloatingTextParticle;

use pocketmine\level\sound\FizzSound;

use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

use mysterybox\tile\MysteryTile;

use mysterybox\MysteryBox;

class MysterySkull extends Entity{
	
	public const NETWORK_ID = Entity::WITHER_SKULL;
	
	/** @var float */
	public $height = 0.4;
	
	/** @var float */
	public $width = 0.4;
	
	/** @var MysteryTile */
	protected $tile;
	
	/** @var MysteryBox */
	protected $mysterybox;
	
	/** @var Player */
	protected $player;
        
	/* @var float */
	protected $max_y = 5;
	
	/** @var int */
	protected $stay_time = 0;
	
	/** @var FloatingTextParticle */
	protected $ftp;
	
	/** @var ItemEntity */
	protected $itemEntity;
	
	/**
	 * @param Player $player
	 * @param MysteryTile $tile
	 * @param MysteryBox $mysterybox
	 */
	
	public function __construct(Player $player, MysteryTile $tile, MysteryBox $mysterybox){
		$this->tile = $tile;
		$this->mysterybox = $mysterybox;
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
		
		$this->getLevel()->addSound(new FizzSound($this->asVector3()));
		
		if($this->max_y > 0 and $this->stay_time == 0){
			$this->motion->y = 0.05;
			$this->max_y -= 0.1;
			
			$this->getLevel()->addParticle(new LavaParticle($this->asVector3()));
		}else{
			if($this->motion->y > 0){
				$this->motion->y = 0;
			}
			
			$this->stay_time++;
		}
		
		if($this->stay_time >= 60){
			$this->motion->y = -0.05;
			$this->max_y  += 0.1;
			
			if($this->max_y == 5){
				$this->flagForDespawn();
				$this->mysterybox->displayAnimation($this->tile, false);
				
				$this->ftp->setInvisible(true);
				if($this->itemEntity !== null){
					$this->itemEntity->flagForDespawn();
				}
				
				foreach($this->ftp->encode() as $pk){
					$this->getLevel()->addChunkPacket($this->x >> 4, $this->z >> 4, $pk);
				}
				
				$this->tile->in_use = false;
			}
		}elseif($this->stay_time == 1){
			$data = $this->mysterybox->grantItem($this->player);
			
			$this->getLevel()->addParticle(new HugeExplodeSeedParticle($this->asVector3()));
			
			$this->ftp = new FloatingTextParticle($this->tile->add(0.5, 1, 0.5), $data[1]);
			
			$this->getLevel()->addParticle($this->ftp);
			$this->getLevel()->broadcastLevelSoundEvent($this->asVector3(), LevelSoundEventPacket::SOUND_PORTAL_TRAVEL);
			
			$this->itemEntity = $this->getLevel()->dropItem($this->ftp->asVector3(), $data[0], new Vector3(0, 0, 0), 8000);
		}
		
		if($this->yaw > 360){
			$this->yaw = 0;
		}else{
			$this->yaw += 15;
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