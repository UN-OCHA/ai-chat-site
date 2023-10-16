<?php

namespace Drupal\ocha_ai_chat\Plugin;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase as CorePluginBase;

/**
 * Base embedding plugin.
 */
abstract class PluginBase extends CorePluginBase implements ContainerFactoryPluginInterface, PluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel(): string {
    $definition = $this->getPluginDefinition();
    return $definition['label'] ?? $this->getPluginId();
  }

}
