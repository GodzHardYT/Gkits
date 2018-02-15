>?php

namespace Gkit;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\entity\Effect;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\Player;
use PiggyCustomEnchants\CustomEnchants\CustomEnchants;

class Kit{

    private $gkit;
    private $data;
    private $name;
    private $cost = 0;
    private $coolDown;
    private $coolDowns = [];
    
    public function __construct(Main $gkit, array $data, string $name){
        $this->gkit = $gkit;
        $this->data = $data;
        $this->name = $name;
        $this->coolDown = $this->getCoolDownMinutes();
        if(isset($this->data["money"]) and $this->data["money"] != 0){
            $this->cost = (int) $this->data["money"];
        }
        if(file_exists($this->gkit->getDataFolder() . "cooldowns/" . strtolower($this->name) . ".sl")){
            $this->coolDowns = unserialize(file_get_contents($this->gkit->getDataFolder() . "cooldowns/" . strtolower($this->name) . ".sl"));
        }
    }
    
    public function getName() : string{
        return $this->name;
    }
    
    public function handleRequest(Player $player) : bool{
        if($this->testPermission($player)){
            if(!isset($this->coolDowns[strtolower($player->getName())])){
                if(!($this->gkit->getConfig()->get("one-gkit-per-life") and isset($this->gkit->hasKit[strtolower($player->getName())]))){
                    if($this->cost){
                        if($this->gkit->economy->grantKit($player, $this->cost)){
                            $this->addTo($player);
                            $player->sendMessage($this->gkit->langManager->getTranslation("sel-gkit", $this->name));
                            return true;
                        }else{
                            $player->sendMessage($this->gkit->langManager->getTranslation("cant-afford", $this->name));
                        }
                    }else{
                        $this->addTo($player);
                        $player->sendMessage($this->gkit->langManager->getTranslation("sel-gkit", $this->name));
                        return true;
                    }
                }else{
                    $player->sendMessage($this->gkit->langManager->getTranslation("one-per-life"));
                }
            }else{
                $player->sendMessage($this->gkit->langManager->getTranslation("cooldown1", $this->name));
                $player->sendMessage($this->gkit->langManager->getTranslation("cooldown2", $this->getCoolDownLeft($player)));
            }
        }else{
            $player->sendMessage($this->gkit->langManager->getTranslation("no-perm", $this->name));
        }
        return false;
    }
	public function addTo(Player $player){
		$inv = $player->getInventory();
		if(count($inv->getContents()) + count($this->data["items"]) > $inv->getSize()){
			$player->sendMessage($this->ak->langManager->getTranslation("inventory-error"));
			return;
		}
		foreach($this->data["items"] as $itemString){
			$inv->setItem($inv->firstEmpty(), $i = $this->loadItem(...explode(":", $itemString)));
		}
		isset($this->data["helmet"]) and $inv->setHelmet($this->loadItem(...explode(":", $this->data["helmet"])));
		isset($this->data["chestplate"]) and $inv->setChestplate($this->loadItem(...explode(":", $this->data["chestplate"])));
		isset($this->data["leggings"]) and $inv->setLeggings($this->loadItem(...explode(":", $this->data["leggings"])));
		isset($this->data["boots"]) and $inv->setBoots($this->loadItem(...explode(":", $this->data["boots"])));
		if(isset($this->data["effects"])){
			foreach($this->data["effects"] as $effectString){
				$e = $this->loadEffect(...explode(":", $effectString));
				if($e !== null){
					$player->addEffect($e);
				}
			}
		}
		if(isset($this->data["commands"]) and is_array($this->data["commands"])){
			foreach($this->data["commands"] as $cmd){
				$this->gkit->getServer()->dispatchCommand(new ConsoleCommandSender(), str_replace("{player}", $player->getName(), $cmd));
			}
		}
		if($this->coolDown){
			$this->coolDowns[strtolower($player->getName())] = $this->coolDown;
		}
		$this->gkit->hasKit[strtolower($player->getName())] = $this;
	}
    private function loadItem(int $id = 0, int $damage = 0, int $count = 1, string $name = "default", ...$enchantments) : Item{
        $item = Item::get($id, $damage, $count);
        if(strtolower($name) !== "default"){
            $item->setCustomName($name);
        }
        $ench = null;
        foreach($enchantments as $key => $name_level){
            if($key % 2 === 0){ //Name expected
                $ench = Enchantment::getEnchantmentByName((string) $name_level);
                if($ench === null){
                    $ench = CustomEnchants::getEnchantmentByName((string) $name_level);
                }
            }elseif($ench !== null){
				if($this->ak->piggyEnchants !== null && $ench instanceof CustomEnchants){
					$this->ak->piggyEnchants->addEnchantment($item, $ench->getName(), (int) $name_level);
				}else{
					$item->addEnchantment(new EnchantmentInstance($ench, (int) $name_level));
				}
			}
        }
        return $item;
    }
    private function loadEffect(string $name = "INVALID", int $seconds = 60, int $amplifier = 1){
        $e = Effect::getEffectByName($name);
        if($e !== null){
            return $e->setDuration($seconds * 20)->setAmplifier($amplifier);
        }
        return null;
    }
    private function getCoolDownMinutes() : int{
        $min = 0;
        if(isset($this->data["cooldown"]["minutes"])){
            $min += (int) $this->data["cooldown"]["minutes"];
        }
        if(isset($this->data["cooldown"]["hours"])){
            $min += (int) $this->data["cooldown"]["hours"] * 60;
        }
        return $min;
    }
    private function getCoolDownLeft(Player $player) : string{
        if(($minutes = $this->coolDowns[strtolower($player->getName())]) < 60){
            return $this->gkit->langManager->getTranslation("cooldown-format1", $minutes);
        }
        if(($modulo = $minutes % 60) !== 0){
            return $this->gkit->langManager->getTranslation("cooldown-format2", floor($minutes / 60), $modulo);
        }
        return $this->gkit->langManager->getTranslation("cooldown-format3", $minutes / 60);
    }
    public function processCoolDown(){
        foreach($this->coolDowns as $player => $min){
            $this->coolDowns[$player] -= 1;
            if($this->coolDowns[$player] <= 0){
                unset($this->coolDowns[$player]);
            }
        }
    }
    private function testPermission(Player $player) : bool{
        return $this->gkit->permManager ? $player->hasPermission("advancedkits." . strtolower($this->name)) : (
            (isset($this->data["users"]) ? in_array(strtolower($player->getName()), $this->data["users"]) : true)
            and
            (isset($this->data["worlds"]) ? in_array(strtolower($player->getLevel()->getName()), $this->data["worlds"]) : true)
        );
    }
    public function save(){
        if(count($this->coolDowns) > 0){
            file_put_contents($this->gkit->getDataFolder() . "cooldowns/" . strtolower($this->name) . ".sl", serialize($this->coolDowns));
        }
    }

}
