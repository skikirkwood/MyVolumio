<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekProfileAddForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for lingotek profiles addition.
 */
class LingotekProfileAddForm extends LingotekProfileFormBase {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    drupal_set_message($this->t('The Lingotek profile has been successfully saved.'));
    $form_state->setRedirect('lingotek.settings');
  }

}