<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsAccountForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabAccountForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_account_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('lingotek.settings');
    $isEnterprise = $this->t('Yes');
    $connectionStatus = $this->t('Inactive');

    if ($config->get('account.plan_type') == 'basic') {
      $isEnterprise = $this->t('No');
    }

    try {
      if ($this->lingotek->getAccountInfo()) {
        $connectionStatus = $this->t('Active');
      }
    } catch(LingotekApiException $exception) {
      drupal_set_message($this->t('There was a problem checking your account status.'), 'warning');
    }

    $statusRow = [
      ['#markup' => $this->t('Status:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $connectionStatus],
      ['#markup' => ''],
    ];
    $planRow = [
      ['#markup' => $this->t('Enterprise:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $isEnterprise],
      ['#markup' => ''],
    ];
    $activationRow = [
      ['#markup' => $this->t('Activation Name:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $config->get('account.login_id')],
      [],
    ];
    $tokenRow = [
      ['#markup' => $this->t('Access Token:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $config->get('account.access_token')],
      ['#markup' => ''],
    ];

    $default_community = $config->get('default.community');
    $default_community_name = $config->get('account.resources.community.' . $default_community);
    $communityRow = [
      ['#markup' => $this->t('Community:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => new FormattableMarkup('@name (@id)', ['@name'=> $default_community_name, '@id' => $default_community])],
      ['#markup' => $this->getLinkGenerator()->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
    ];

    $default_workflow = $config->get('default.workflow');
    $default_workflow_name = $config->get('account.resources.workflow.' . $default_workflow);
    $workflowRow = [
      ['#markup' => $this->t('Default Workflow:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => new FormattableMarkup('@name (@id)', ['@name'=> $default_workflow_name, '@id' => $default_workflow])],
      ['#markup' => $this->getLinkGenerator()->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
    ];

    $default_project = $config->get('default.project');
    $default_project_name = $config->get('account.resources.project.' . $default_project);
    $projectRow = [
      ['#markup' => $this->t('Default Project:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => new FormattableMarkup('@name (@id)', ['@name'=> $default_project_name, '@id' => $default_project])],
      ['#markup' => $this->getLinkGenerator()->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
    ];

    $default_vault = $config->get('default.vault');
    $default_vault_name = $config->get('account.resources.vault.' . $default_vault);

    $vaultRow = [
      ['#markup' => $this->t('Default Vault:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $default_vault ? new FormattableMarkup('@name (@id)', ['@name'=> $default_vault_name, '@id' => $default_vault]) : ''],
      ['#markup' => $this->getLinkGenerator()->generate($this->t('Edit defaults'), Url::fromRoute('lingotek.edit_defaults'))],
    ];

    $tmsRow = [
      ['#markup' => $this->t('Lingotek TMS Server:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => $config->get('account.use_production') ? $config->get('account.host') : $config->get('account.sandbox_host')],
      ['#markup' => ''],
    ];
    $gmcRow = [
      ['#markup' => $this->t('Lingotek GMC Server:'), '#prefix' => '<b>', '#suffix' => '</b>'],
      ['#markup' => 'https://gmc.lingotek.com'],
      ['#markup' => ''],
    ];
    
    $accountTable = [
      '#type' => 'table',
      '#empty' => $this->t('No Entries'),
    ];

    $accountTable['status_row'] = $statusRow;
    $accountTable['plan_row'] = $planRow;
    $accountTable['activation_row'] = $activationRow;
    $accountTable['token_row'] = $tokenRow;
    $accountTable['community_row'] = $communityRow;
    $accountTable['workflow_row'] = $workflowRow;
    $accountTable['project_row'] = $projectRow;
    $accountTable['vault_row'] = $vaultRow;
    $accountTable['tms_row'] = $tmsRow;
    $accountTable['gmc_row'] = $gmcRow;

    $form['account'] = [
      '#type' => 'details',
      '#title' => $this->t('Account'),
    ];

    $form['account']['account_table'] = $accountTable;
    $form['account']['actions']['#type'] = 'actions';
    $form['account']['actions']['disconnect'] = [
      '#type' => 'submit',
      '#value' => $this->t('Disconnect'),
      '#button_type' => 'danger',
      '#submit' => [[$this, 'disconnect']],
    ];

     return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function disconnect(array &$form, FormStateInterface $form_state) {
    // Redirect to the confirmation form.
    $form_state->setRedirect('lingotek.account_disconnect');
  }

}
