<?php

namespace Drupal\ocha_ai_chat\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for the text extractor plugins.
 */
class PluginManagerBase extends DefaultPluginManager implements PluginManagerInterface {

  /**
   * Static cache for the plugin instances.
   *
   * @var array
   */
  protected array $instances = [];

  /**
   * {@inheritdoc}
   */
  public function getAvailablePlugins(): array {
    foreach ($this->getDefinitions() as $plugin_id => $plugin_definition) {
      if (!isset($this->instances[$plugin_id])) {
        $this->instances[$plugin_id] = $this->createInstance($plugin_id, $plugin_definition);
      }
    }
    return $this->instances;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(string $plugin_id): PluginInterface {
    if (!isset($this->instances[$plugin_id])) {
      $plugin_definition = $this->getDefinition($plugin_id);
      $this->instances[$plugin_id] = $this->createInstance($plugin_id, $plugin_definition);
    }
    return $this->instances[$plugin_id];
  }

}
