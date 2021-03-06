<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekProfileEditForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for lingotek profiles edition.
 */
class LingotekProfileEditForm extends LingotekProfileFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_profile_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    drupal_set_message($this->t('The Lingotek profile has been successfully edited.'));
    $form_state->setRedirect('lingotek.settings');
  }

}