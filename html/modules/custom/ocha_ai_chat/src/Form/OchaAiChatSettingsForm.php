<?php

namespace Drupal\ocha_ai_chat\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the Ocha AI Chat module.
 */
class OchaAiChatSettingsForm extends FormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $defaults = $this->state->get('ocha_ai_chat.settings', []);

    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Provider'),
      '#description' => $this->t('Default AI service provider.'),
      '#options' => [
        'amazon_bedrock' => $this->t('Amazon Bedrock'),
        'azure_openai' => $this->t('Azure OpenAI'),
      ],
      '#default_value' => $defaults['provider'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['reliefweb_river_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default ReliefWeb river URL'),
      '#description' => $this->t('Default filtered list of AI content to chat against from https://reliefweb.int/updates.'),
      '#default_value' => $defaults['reliefweb_river_url'] ?? NULL,
      '#required' => TRUE,
    ];

    $form['reliefweb_api_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default AI API limit'),
      '#description' => $this->t('Default maximum number of documents to retrieve from the AI API.'),
      '#default_value' => $defaults['reliefweb_api_limit'] ?? 10,
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
      '#button_type' => 'primary',
    ];
    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = [
      'provider' => $form_state->getValue('provider'),
      'reliefweb_river_url' => $form_state->getValue('reliefweb_river_url'),
      'reliefweb_api_limit' => $form_state->getValue('reliefweb_api_limit'),
    ];
    $this->state->set('ocha_ai_chat.settings', $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ocha_ai_chat_settings';
  }

}
