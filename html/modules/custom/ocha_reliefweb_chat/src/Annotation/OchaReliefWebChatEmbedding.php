<?php

namespace Drupal\ocha_reliefweb_chat\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a OCHA ReliefWeb Chat embedding client plugin annotation object.
 *
 * Plugin Namespace: Plugin\ocha_reliefweb_chat\Embedding.
 *
 * @see \Drupal\ocha_reliefweb_chat\Plugin\EmbeddingPluginBase
 * @see \Drupal\ocha_reliefweb_chat\Plugin\EmbeddingPluginInterface
 * @see \Drupal\ocha_reliefweb_chat\Plugin\EmbeddingPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class OchaReliefWebChatEmbedding extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   * @ingroup plugin_translatable
   */
  public Translation $label;

  /**
   * A short description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   * @ingroup plugin_translatable
   */
  public Translation $description;

}
