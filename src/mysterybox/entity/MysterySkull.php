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

use pocketmine\level\particle\HeartParticle;
use pocketmine\level\particle\HugeExplodeSeedParticle;
use pocketmine\level\particle\FloatingTextParticle;

use pocketmine\event\entity\EntityDamageEvent;

use pocketmine\level\sound\PopSound;

use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;

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
	
	/** @var int */
	protected $item_eid = -1;
	
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
	 * @param EntityDamageEvent $source
	 */
	
	public function attack(EntityDamageEvent $source) : void{
		$source->setCancelled();
		
	}
	
	/**
	 * @param int $diff
	 *
	 * @return bool
	 */
	
	public function entityBaseTick(int $diff = 1) : bool{
		$return = parent:: entityBaseTick($diff);
		
		if($this->tile->isClosed() or $this->player->isClosed()){
			$this->flagForDespawn();
			
			if($this->tile->isClosed() == false){
				$this->tile->in_use = false;
			}
			
			if($this->item_eid !== -1){
				$pk = new RemoveActorPacket;
				$pk->entityUniqueId = $this->item_eid;
				
				$this->getLevel()->addChunkPacket($this->x >> 4, $this->z >> 4, $pk);
			}
			
			if($this->ftp !== null){
				$this->ftp->setInvisible(true);
				
				foreach($this->ftp->encode() as $pk){
					$this->getLevel()->addChunkPacket($this->x >> 4, $this->z >> 4, $pk);
				}
			}
			
			return true;
		}
		
		$this->getLevel()->addSound(new PopSound($this->asVector3()));
		
		if($this->max_y > 0 and $this->stay_time == 0){
			$this->motion->y = 0.05;
			$this->max_y -= 0.1;
		}else{
			if($this->motion->y > 0){
				$this->motion->y = 0;
			}
			
			$this->stay_time++;
		}
		
		$this->getLevel()->addParticle(new HeartParticle($this->asVector3()));
		
		if($this->stay_time >= 60){
			$this->motion->y = -0.05;
			$this->max_y  += 0.1;
			
			if($this->max_y == 5){
				$this->flagForDespawn();
				$this->mysterybox->displayAnimation($this->tile, false);
				
				$this->ftp->setInvisible(true);
				
				if($this->item_eid !== -1){
					$pk = new RemoveActorPacket;
					$pk->entityUniqueId = $this->item_eid;
				
					$this->getLevel()->addChunkPacket($this->x >> 4, $this->z >> 4, $pk);
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
			$this->getLevel()->broadcastLevelSoundEvent($this->asVector3(), LevelSoundEventPacket::SOUND_BLOCK_BELL_HIT);
			
			$this->item_eid = Entity::$entityCount++;
			
			$pk  = new AddItemActorPacket;
			$pk->entityRuntimeId = $this->item_eid;
			$pk->item = $data[0];
			$pk->position = $this->ftp->asVector3();
			
			$this->getLevel()->addChunkPacket($this->x >> 4, $this->z >> 4, $pk);
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
	
	/**
	 * @param void
	 */
	
	public function close() : void{
		parent::close();
		
		if(is_null($this->tile) == false){
			$this->tile->in_use = false;
		}
	}
}
