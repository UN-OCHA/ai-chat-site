<?php

namespace Drupal\ocha_ai_chat\Plugin;

/**
 * Base embedding plugin.
 */
abstract class EmbeddingPluginBase extends PluginBase implements EmbeddingPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getPluginType(): string {
    return 'embedding';
  }

  /**
   * {@inheritdoc}
   */
  public function getDimensions(): int {
    return $this->getPluginSetting('dimensions');
  }

}
