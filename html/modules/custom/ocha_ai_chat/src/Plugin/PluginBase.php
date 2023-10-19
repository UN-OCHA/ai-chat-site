<?php

namespace Drupal\ocha_ai_chat\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase as CorePluginBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base embedding plugin.
 */
abstract class PluginBase extends CorePluginBase implements ContainerFactoryPluginInterface, PluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The plugin settings.
   *
   * @var array
   */
  protected array $settings;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel(): string {
    $definition = $this->getPluginDefinition();
    return $definition['label'] ?? $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLogger(): LoggerInterface {
    if (!isset($this->logger)) {
      $this->logger = $this->loggerFactory->get(implode('.', [
        'ocha_ai_chat',
        $this->getPluginType(),
        $this->getPluginId(),
      ]));
    }
    return $this->logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginSettings(): array {
    if (!isset($this->settings)) {
      // @todo check how to make the plugin configurable.
      $this->settings = $this->configFactory
        ->get('ocha_ai_chat.settings')
        ?->get($this->getPluginType() . '.' . $this->getPluginId()) ?? [];
    }
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginSetting(string $key, mixed $default = NULL, bool $throw_if_null = TRUE): mixed {
    $setting = $this->getPluginSettings()[$key] ?? $default;
    if (is_null($setting) && $throw_if_null) {
      throw new \Exception(strtr('Missing @key for @type plugin @id', [
        '@key' => $key,
        '@type' => $this->getPluginType(),
        '@id' => $this->getPluginId(),
      ]));
    }
    return $setting;
  }

}
