<?php

namespace Drupal\ocha_reliefweb_chat\Plugin;

/**
 * Base interface for the ocha_reliefweb_chat plugins.
 */
interface PluginManagerInterface {

  /**
   * Get the available completion plugins.
   *
   * @return \Drupal\ocha_reliefweb_chat\Plugin\PluginInterface[]
   *   List of plugins.
   */
  public function getAvailablePlugins(): array;

  /**
   * Get the instance of the plugin with the given ID.
   *
   * @param string $plugin_id
   *   Plugin ID.
   *
   * @return \Drupal\ocha_reliefweb_chat\Plugin\PluginInterface
   *   Plugin instance.
   */
  public function getPlugin(string $plugin_id): PluginInterface;

}
