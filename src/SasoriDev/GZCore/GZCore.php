<?php
// CORRECT NAMESPACE
namespace SasoriDev\GZCore;

use pocketmine\plugin\PluginBase;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginManager;
use pocketmine\utils\Config;
use ReflectionClass;

class GZCore extends \pocketmine\plugin\PluginBase {

    private Config $config;
    private array $managedPlugins = [];
    private string $configsRoot;

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->configsRoot = $this->getDataFolder() . "plugin_configs/";

        $this->initializeDirectories();
        $this->loadCorePlugins();
        $this->managePluginStates();
    }

    private function initializeDirectories(): void {
        @mkdir($this->getDataFolder() . "plugins/", 0777, true);
        @mkdir($this->configsRoot, 0777, true);
    }

    private function loadCorePlugins(): void {
        $pluginManager = $this->getServer()->getPluginManager();
        $corePluginDir = $this->getDataFolder() . "plugins/";

        foreach(scandir($corePluginDir) as $file) {
            $path = $corePluginDir . $file;
            if(in_array($file, [".", ".."]) || is_dir($path)) continue;

            try {
                $plugin = $pluginManager->loadPlugin($path);
                if($plugin !== null) {
                    $this->setupPluginEnvironment($plugin);
                    $this->managedPlugins[$plugin->getName()] = $plugin;
                    $this->getLogger()->info("Loaded: " . $plugin->getName());
                }
            } catch(\Throwable $e) {
                $this->getLogger()->error("Load failed: " . $e->getMessage());
            }
        }
    }

    private function setupPluginEnvironment(Plugin $plugin): void {
        $pluginConfigDir = $this->configsRoot . $plugin->getName() . "/";

        try {
            $reflection = new ReflectionClass(Plugin::class);
            $dataFolder = $reflection->getProperty("dataFolder");
            $dataFolder->setValue($plugin, $pluginConfigDir);

            if(!file_exists($pluginConfigDir)) {
                mkdir($pluginConfigDir, 0777, true);
            }
        } catch(\ReflectionException $e) {
            $this->getLogger()->error("Config setup failed: " . $e->getMessage());
        }
    }

    private function managePluginStates(): void {
        $pluginManager = $this->getServer()->getPluginManager();
        $pluginsConfig = $this->config->get("plugins", []);

        foreach($this->managedPlugins as $name => $plugin) {
            if($plugin === $this) continue;

            $status = $pluginsConfig[$name] ?? false;

            if($status && !$plugin->isEnabled()) {
                $pluginManager->enablePlugin($plugin);
            } elseif(!$status && $plugin->isEnabled()) {
                $pluginManager->disablePlugin($plugin);
            }
        }
    }

    public function onDisable(): void {
        $this->config->save();
    }
}}
