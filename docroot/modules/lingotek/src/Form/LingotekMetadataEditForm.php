<?php

namespace Drupal\lingotek\Form;


use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\Helpers\LingotekManagementFormHelperTrait;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekSetupTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LingotekMetadataEditForm extends ContentEntityForm {

  use LingotekSetupTrait;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   */
  protected $translationService;

  /**
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Constructs a new LingotekManagementForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *  The language-locale mapper.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The Lingotek content translation service.
   * @param string $entity_type_id
   *   The entity type id.
   */
  public function __construct(EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager, LanguageLocaleMapperInterface $language_locale_mapper, LingotekContentTranslationServiceInterface $translation_service, ModuleHandlerInterface $module_handler, $entity_type_id) {
    parent::__construct($entity_manager);

    $this->languageManager = $language_manager;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->translationService = $translation_service;
    $this->entityTypeId = $entity_type_id;

    $this->setModuleHandler($module_handler);

    $this->lingotek = \Drupal::service('lingotek');
    $this->operation = 'lingotek_metadata';

    $entity = $this->getEntityFromRouteMatch($this->getRouteMatch(), $this->entityTypeId);
    $this->setEntity($entity);

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('language_manager'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('lingotek.content_translation'),
      $container->get('module_handler'),
      \Drupal::routeMatch()->getParameter('entity_type_id')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    // $form = parent::buildForm($form, $form_state);
    $entity = $this->getEntity();
    $lingotek_document_id = $this->translationService->getDocumentId($entity);
    $source_status = $this->translationService->getSourceStatus($entity);
    $form['metadata']['notice'] = [
      '#markup' => $this->t('Editing the metadata manually can cause diverse errors. If you find yourself using it often, please contact the module maintainers because you may have hit a bug.'),
      '#prefix' => '<span class="warning">',
      '#suffix' => '</span',
    ];
    $form['metadata']['lingotek_document_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Lingotek Document ID'),
      '#default_value' => $lingotek_document_id,
    ];
    $form['metadata']['lingotek_source_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Lingotek Source Status'),
      '#default_value' => $source_status,
      '#options' => $this->getLingotekStatusesOptions(),
    ];
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $langcode => $language) {
      $form['metadata']['lingotek_target_status'][$langcode] = [
        '#type' => 'select',
        '#title' => $this->t('Lingotek Target Status: ') . $language->getName(),
        '#default_value' => $this->translationService->getTargetStatus($entity, $langcode),
        '#options' => $this->getLingotekStatusesOptions(),
      ];
    }

    $form['actions'] = [];
    $form['actions']['save_metadata'] = array(
      '#type' => 'submit',
      '#value' => t('Save metadata'),
      '#button_type' => 'primary',
      '#limit_validation_errors' => array(),
      '#submit' => [[$this, 'saveMetadata']],
    );
    return $form;
  }

  /**
   * Submit handler that saves the metadata of this content entity.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function saveMetadata(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();

    $input = $form_state->getUserInput();
    $lingotek_document_id = $input['lingotek_document_id'];
    $source_status = $input['lingotek_source_status'];

    // We need to delete the key before inserting it in the table.
    \Drupal::database()->delete('lingotek_content_metadata')
      ->condition('document_id', $lingotek_document_id)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id',  $entity->id())
      ->execute();

    $this->translationService->setDocumentId($entity, $lingotek_document_id);
    $this->translationService->setSourceStatus($entity, $source_status);
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $this->translationService->setTargetStatus($entity, $langcode, $input[$langcode]);
    }

    drupal_set_message($this->t('Metadata saved successfully'));
  }


  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'lingotek_metadata_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $form_id = $this->entityTypeId;
    $form_id .= '_' . $this->getBaseFormId();
    return $form_id;
  }

  public function getLingotekStatusesOptions() {
    return [
      Lingotek::STATUS_CURRENT => $this->t('Current'),
      Lingotek::STATUS_EDITED => $this->t('Edited'),
      Lingotek::STATUS_IMPORTING => $this->t('Importing'),
      Lingotek::STATUS_PENDING => $this->t('Pending'),
      Lingotek::STATUS_READY => $this->t('Ready'),
      Lingotek::STATUS_REQUEST => $this->t('Request'),
      Lingotek::STATUS_UNTRACKED => $this->t('Untracked'),
    ];
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Not needed, we have our own handler.
  }


  protected function getEditedFieldNames(FormStateInterface $form_state) {
    return [];
  }

  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Do nothing. We don't want to alter the entity.
  }

  protected function flagViolations(EntityConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    // ToDo
    // We ignore violations.
  }

}
