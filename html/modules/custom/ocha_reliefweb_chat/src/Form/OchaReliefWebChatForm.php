<?php

namespace Drupal\ocha_reliefweb_chat\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ocha_reliefweb_chat\Services\OchaReliefWebChat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for the Ocha ReliefWeb Chat module.
 */
class OchaReliefWebChatForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The OCHA ReliefWeb chat service.
   *
   * @var Drupal\ocha_reliefweb_chat\Services\OchaReliefWebChat
   */
  protected $reliefWebChat;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\ocha_reliefweb_chat\Services\OchaReliefWebChat $reliefweb_chat
   *   The OCHA ReliefWeb chat service.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    StateInterface $state,
    OchaReliefWebChat $reliefweb_chat
  ) {
    $this->currentUser = $current_user;
    $this->state = $state;
    $this->reliefWebChat = $reliefweb_chat;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('state'),
      $container->get('ocha_reliefweb_chat.chat')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $defaults = $this->state->get('ocha_reliefweb_chat.settings', []);

    // @todo set maxlength.
    $form['reliefweb_river_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default ReliefWeb river URL'),
      '#description' => $this->t('Filtered list of ReliefWeb content to chat against from https://reliefweb.int/updates.'),
      '#default_value' => $form_state->getValue('reliefweb_river_url') ?? $defaults['reliefweb_river_url'] ?? NULL,
      '#required' => TRUE,
      '#access' => $this->currentUser->hasPermission('access reliefweb chat advanced features'),
    ];

    $form['reliefweb_api_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document limit'),
      '#description' => $this->t('Maximum number of documents to chat against.'),
      '#default_value' => $form_state->getValue('reliefweb_api_limit') ?? $defaults['reliefweb_api_limit'] ?? 10,
      '#required' => TRUE,
      '#access' => $this->currentUser->hasPermission('access reliefweb chat advanced features'),
    ];

    $form['question'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Question'),
      '#default_value' => $form_state->getValue('question') ?? NULL,
    ];

    $form['answer'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="answer"><h2>{% trans %}Answer{% endtrans %}</h2><p>{{ answer }}</p></div>',
      '#context' => [
        'answer' => $form_state->getValue('answer') ?? '',
      ],
    ];

    $form['reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset'),
      '#description' => $this->t('Checking this will delete the index containing the data extracted from the ReliefWeb documents. Only use this for test purposes.'),
      '#default_value' => $form_state->getValue('reset') ?? NULL,
      '#access' => $this->currentUser->hasPermission('access reliefweb chat advanced features'),
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $river_url = $form_state->getValue('reliefweb_river_url');
    $question = $form_state->getValue('question');
    $reset = $form_state->getValue('reset');

    // Get the answer to the question.
    // @todo use server events etc. for a better UX.
    $answer = $this->reliefWebChat->answer($question, $river_url, $reset);
    $form_state->setValue('answer', $answer);

    // Rebuild the form so that it is reloaded with the inputs from the user
    // as well as the AI answer.
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ocha_reliefweb_chat';
  }

}
