<?php

namespace Drupal\ocha_ai_chat\Plugin;

/**
 * Base text splitter plugin.
 */
abstract class TextSplitterPluginBase extends PluginBase implements TextSplitterPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getPluginType(): string {
    return 'text_splitter';
  }

}
