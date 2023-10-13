<?php

namespace Drupal\ocha_reliefweb_chat\Plugin;

/**
 * Interface for the ocha_reliefweb_chat plugins.
 */
interface PluginInterface {

  /**
   * Get the plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function getPluginLabel(): string;

}
