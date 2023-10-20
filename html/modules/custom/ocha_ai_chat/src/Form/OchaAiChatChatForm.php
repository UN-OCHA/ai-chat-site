<?php

namespace Drupal\ocha_ai_chat\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ocha_ai_chat\Services\OchaAiChat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Chat form for the Ocha AI Chat module.
 */
class OchaAiChatChatForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The OCHA AI chat service.
   *
   * @var Drupal\ocha_ai_chat\Services\OchaAiChat
   */
  protected OchaAiChat $ochaAiChat;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\ocha_ai_chat\Services\OchaAiChat $ocha_ai_chat
   *   The OCHA AI chat service.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    StateInterface $state,
    OchaAiChat $ocha_ai_chat
  ) {
    $this->currentUser = $current_user;
    $this->state = $state;
    $this->ochaAiChat = $ocha_ai_chat;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('state'),
      $container->get('ocha_ai_chat.chat')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $defaults = $this->ochaAiChat->getSettings();

    $form['source_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default ReliefWeb river URL'),
      '#description' => $this->t('Filtered list of ReliefWeb content from https://reliefweb.int/updates to chat against.'),
      '#default_value' => $form_state->getValue('source_url') ?? $defaults['plugins']['source']['url'] ?? NULL,
      '#required' => TRUE,
      '#access' => $this->currentUser->hasPermission('access ocha ai chat advanced features'),
      '#maxlength' => 2048,
    ];

    $form['source_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document limit'),
      '#description' => $this->t('Maximum number of documents to chat against.'),
      '#default_value' => $form_state->getValue('source_url') ?? $defaults['plugins']['source']['limit'] ?? 10,
      '#required' => TRUE,
      '#access' => $this->currentUser->hasPermission('access ocha ai chat advanced features'),
    ];

    $form['question'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Question'),
      '#default_value' => $form_state->getValue('question') ?? NULL,
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced options'),
      '#open' => FALSE,
    ];

    // Completion plugin.
    $completion_options = array_map(function ($plugin) {
      return $plugin->getPluginLabel();
    }, $this->ochaAiChat->getCompletionPluginManager()->getAvailablePlugins());

    $completion_default = $form_state->getValue('completion_plugin_id') ??
      $defaults['plugins']['completion']['plugin_id'] ??
      key($completion_options);

    $form['advanced']['completion_plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('AI service'),
      '#description' => $this->t('Select the AI service to use to generate the answer.'),
      '#options' => $completion_options,
      '#default_value' => $completion_default,
      '#required' => TRUE,
    ];

    $form['advanced']['reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset'),
      '#description' => $this->t('Checking this will delete the index containing the data extracted from the ReliefWeb documents. Only use this for test purposes.'),
      '#default_value' => $form_state->getValue('reset') ?? NULL,
      '#access' => $this->currentUser->hasPermission('access ocha ai chat advanced features'),
    ];

    // Answer.
    $answer = $form_state->getValue('answer');
    $form['result'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Answer'),
      '#access' => !empty($answer),
    ];
    $form['result']['answer'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ answer }}</p>',
      '#context' => [
        'answer' => $answer,
      ],
    ];

    // @todo add field with the result of the question.
    // @todo replace with a more interesting UI.
    // @todo add ajax submission.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask'),
      '#description' => $this->t('It may take several minutes to get the answer.'),
      '#button_type' => 'primary',
    ];

    // @todo check if we need a theme.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $source_url = $form_state->getValue('source_url');
    $source_limit = $form_state->getValue('source_limit');
    $question = $form_state->getValue('question');
    $reset = $form_state->getValue('reset');

    $completion_plugin_id = $form_state->getValue('completion_plugin_id');
    if (isset($completion_plugin_id)) {
      $completion_plugin = $this->ochaAiChat
        ->getCompletionPluginManager()
        ->getPlugin($completion_plugin_id);
    }
    else {
      $completion_plugin = NULL;
    }

    // Get the answer to the question.
    // @todo use server events etc. for a better UX.
    $answer = $this->ochaAiChat->answer($question, $source_url, $source_limit, $reset, $completion_plugin);
    $form_state->setValue('answer', $answer);

    // Rebuild the form so that it is reloaded with the inputs from the user
    // as well as the AI answer.
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ocha_ai_chat_chat_form';
  }

}
