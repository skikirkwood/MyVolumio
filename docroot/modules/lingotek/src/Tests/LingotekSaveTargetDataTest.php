<?php

namespace Drupal\lingotek\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests the Lingotek content service saves data to entities correctly.
 *
 * @group lingotek
 */
class LingotekSaveTargetDataTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node'];

  protected function setUp() {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create Article node type.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));

    // Add languages.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_ES')
      ->save();
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_DE')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

  }

  public function testRightRevisionsAreSavedIfThereIsMetadata() {
    // Create a node.
    /** @var NodeInterface $node */
    $node = $this->createNode([
      'type' => 'article',
      'title' => 'Revision 1'
    ]);

    // Create a new revision.
    $node->setTitle('Revision 2');
    $node->setNewRevision();
    $node->save();

    // Create a third one.
    $node->setTitle('Revision 3');
    $node->setNewRevision();
    $node->save();

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $es_data = [
      'title' => [0 => ['value' => 'Revision 2 ES']],
      'body' => [0 => ['value' => 'es body']],
      '_lingotek_metadata' => [
        '_entity_type_id' => 'node',
        '_entity_id' => 1,
        '_entity_revision' => 2,
      ]
    ];
    $translation_service->saveTargetData($node, 'es', $es_data);

    $node = \Drupal::entityManager()->getStorage('node')->load(1);
    $node = $node->getTranslation('es');

    $this->assertEqual('es body', $node->body->value, 'The body is translated correctly.');
    $this->assertEqual('Revision 2 ES', $node->getTitle(), 'The title in the revision translation is the one given.');
    $this->assertEqual(3, $node->getRevisionId(), 'The translation is saved in the newest revision.');
  }

  public function testFieldsAreNotExtractedIfNotTranslatableEvenIfStorageIsTranslatable() {
    // Ensure field storage is translatable.
    $field_storage = FieldStorageConfig::loadByName('node', 'body');
    $field_storage->setTranslatable(TRUE)->save();

    // Ensure field instance is translatable.
    $field = FieldConfig::loadByName('node', 'article', 'body');
    $field->setTranslatable(TRUE)->save();

    // Ensure changes were saved correctly.
    $field_storage = FieldStorageConfig::loadByName('node', 'body');
    $field = FieldConfig::loadByName('node', 'article', 'body');
    $this->assertTrue($field_storage->isTranslatable(), 'Field storage is translatable.');
    $this->assertTrue($field->isTranslatable(), 'Field instance is translatable.');

    // Create a node.
    $this->createNode([
      'type' => 'article',
    ]);

    $node = Node::load(1);
    $title = $node->getTranslation('en')->getTitle();
    $body = $node->getTranslation('en')->body->value;
    $this->verbose($body);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $es_data = [
      'title' => [0 => ['value' => 'es title']],
      'body' => [0 => ['value' => 'es body']]
    ];

    $node = $translation_service->saveTargetData($node, 'es', $es_data);

    $this->assertEqual('es body', $node->getTranslation('es')->body->value, 'The body is translated if the field is translatable.');
    $this->assertEqual($body, $node->getTranslation('en')->body->value, 'The body in the original language is not overriden.');
    $this->assertEqual('es title', $node->getTranslation('es')->getTitle(), 'The title in the translation is the one given.');
    $this->assertEqual($title, $node->getTranslation('en')->getTitle(), 'The title in the original language is not overriden.');

    // Make the field as not translatable.
    $field->setTranslatable(FALSE)->save();
    $this->assertTrue($field_storage->isTranslatable(), 'Field storage is translatable.');
    $this->assertFalse($field->isTranslatable(), 'Field instance is not translatable.');

    $de_data = [
      'title' => [0 => ['value' => 'de title']],
      'body' => [0 => ['value' => 'de body']]
    ];
    // If the field is not translatable, the field is not there.
    $node = $translation_service->saveTargetData($node, 'de', $de_data);

    $this->assertEqual($body, $node->getTranslation('de')->body->value, 'The body is not written if the field is not translatable.');
    $this->assertEqual($body, $node->getTranslation('en')->body->value, 'The body is not overridden if the field is not translatable.');
    $this->assertEqual('de title', $node->getTranslation('de')->getTitle(), 'The title in the translation is the one given.');
    $this->assertEqual($title, $node->getTranslation('en')->getTitle(), 'The title in the original language is not overriden.');
  }

}
