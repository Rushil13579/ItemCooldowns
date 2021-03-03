<?php

namespace Rushil13579\ItemCooldowns;

use pocketmine\{Server, Player};

use pocketmine\plugin\PluginBase;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\item\Consumable;

use pocketmine\utils\{Config, TextFormat as C};

class Main extends PluginBase implements Listener {

  public $cfg;

  public const PLUGIN_PREFIX = '§3[§bItemCooldowns§3]';

  public function onEnable(){
    $this->getServer()->getPluginManager()->registerEvents($this, $this);

    $this->saveDefaultConfig();
    @mkdir($this->getDataFolder() . 'Cooldowns/');

    $this->cfg = $this->getConfig();

    $this->versionCheck();
  }

  public function versionCheck(){
    if($this->cfg->get('version') !== '1.0.0'){
      $this->getLogger()->warning('§cThe configuration file is outdated and due to this the plugin might malfunction! Please delete the current configruation file and restart your server to install the latest one');
    }
  }

  /**
  *@priority HIGHEST
  **/

  public function onConsume(PlayerItemConsumeEvent $event){
    if($event->isCancelled()){
      return null;
    }

    $player = $event->getPlayer();
    $item = $event->getItem();

    $check = $this->cooldownCheck($player, $item);
    if($check !== null){
      $event->setCancelled();
    }
  }

  /**
  *@priority HIGHEST
  **/

  public function onInteract(PlayerInteractEvent $event){
    if($event->isCancelled()){
      return null;
    }

    if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_AIR and $event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
      return null;
    }

    $player = $event->getPlayer();
    $item = $event->getItem();

    $check = $this->cooldownCheck($player, $item);
    if($check !== null){
      $event->setCancelled();
    }
  }

  public function cooldownCheck($player, $item){
    if($player->hasPermission('itemcooldowns.bypass')){
      return null;
    }

    if(in_array($player->getLevel()->getName(), $this->cfg->get('exempted-worlds'))){
      return null;
    }

    $itemData = $item->getId() . ':' . $item->getDamage();
    $config = $this->cfg->get('cooldowns');

    if($item instanceof Consumable){
      return null;
    }

    if(!isset($config[$itemData])){
      return null;
    }

    if(!is_numeric($config[$itemData])){
      $this->getLogger()->warning("§cCooldown for $itemData is not numeric!");
      return null;
    }

    $cooldown = $this->getCooldown($player, $item);
    if($cooldown !== null){
      $remaining = (int)$cooldown;
      $hours = floor($remaining/3600);
      $minutes = floor(($remaining/60) % 60);
      $seconds = $remaining % 60;
      $msg = str_replace(['{PLUGIN_PREFIX}', '{HOURS}', '{MINUTES}', '{SECONDS}'], [self::PLUGIN_PREFIX, $hours, $minutes, $seconds], $this->cfg->get('cooldown-msg'));
      $player->sendMessage(C::colorize($msg));
      return ' ';
    }
  }

  public function getCooldown($player, $item){
    if(!file_exists($this->getDataFolder() . 'Cooldowns/' . strtolower($player->getName()))){
      $this->generateFile($player);
      $this->addCooldown($player, $item);
      return null;
    }

    $file = new Config($this->getDataFolder() . 'Cooldowns/' . strtolower($player->getName()), Config::YAML);
    $itemData = $item->getId() . ':' . $item->getDamage();

    if(!$file->exists($itemData)){
      $this->addCooldown($player, $item);
      return null;
    }

    $time = $file->get($itemData);
    if($time < time()){
      $this->addCooldown($player, $item);
      return null;
    }

    $remaining = $time - time();
    return $remaining;
  }

  public function addCooldown($player, $item){
    $file = new Config($this->getDataFolder() . 'Cooldowns/' . strtolower($player->getName()), Config::YAML);
    $itemData = $item->getId() . ':' . $item->getDamage();
    $config = $this->cfg->get('cooldowns');
    $time = time() + $config[$itemData];
    $file->set($itemData, $time);
    $file->save();
  }

  public function generateFile($player){
    new Config($this->getDataFolder() . 'Cooldowns/' . strtolower($player->getName()), Config::YAML);
  }
}
