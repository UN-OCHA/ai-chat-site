<?php

namespace Drupal\ocha_ai_chat\Plugin;

/**
 * Interface for the ocha_ai_chat plugins.
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
