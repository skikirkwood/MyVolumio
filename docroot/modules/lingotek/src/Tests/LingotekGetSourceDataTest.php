<?php

namespace Drupal\lingotek\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;

/**
 * Tests the Lingotek content service extract data from entities correctly.
 *
 * @group lingotek
 */
class LingotekGetSourceDataTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'image'];

  protected function setUp() {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create Article node type.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    \Drupal::service('entity.definition_update_manager')->applyUpdates();

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

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
    $node = $this->createNode([
        'type' => 'article',
      ]);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $serialized_node = $translation_service->getSourceData($node);
    $this->assertTrue(isset($serialized_node['body']), 'The body is included in the extracted data.');

    // Make the field as not translatable.
    $field->setTranslatable(FALSE)->save();
    $this->assertTrue($field_storage->isTranslatable(), 'Field storage is translatable.');
    $this->assertFalse($field->isTranslatable(), 'Field instance is not translatable.');

    // If the field is not translatable, the field is not there.
    $translation_service = \Drupal::service('lingotek.content_translation');
    $serialized_node = $translation_service->getSourceData($node);
    $this->assertFalse(isset($serialized_node['body']), 'The body is not included in the extracted data.');
  }

  public function testContentEntityMetadataIsIncluded() {
    // Create a node.
    $node = $this->createNode([
      'type' => 'article',
    ]);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $serialized_node = $translation_service->getSourceData($node);
    $this->assertTrue(isset($serialized_node['_lingotek_metadata']), 'The Lingotek metadata is included in the extracted data.');
    $this->assertEqual('node', $serialized_node['_lingotek_metadata']['_entity_type_id'], 'Entity type id is included as metadata.');
    $this->assertEqual(1, $serialized_node['_lingotek_metadata']['_entity_id'], 'Entity id is included as metadata.');
    $this->assertEqual(1, $serialized_node['_lingotek_metadata']['_entity_revision'], 'Entity revision id is included as metadata.');

    $node->setNewRevision();
    $node->setTitle($this->randomString(10));
    $node->save();

    $serialized_node = $translation_service->getSourceData($node);
    $this->assertTrue(isset($serialized_node['_lingotek_metadata']), 'The Lingotek metadata is included in the extracted data.');
    $this->assertEqual('node', $serialized_node['_lingotek_metadata']['_entity_type_id'], 'Entity type id is included as metadata.');
    $this->assertEqual(1, $serialized_node['_lingotek_metadata']['_entity_id'], 'Entity id is included as metadata.');
    $this->assertEqual(2, $serialized_node['_lingotek_metadata']['_entity_revision'], 'Entity revision id is included as metadata, and has changed.');
  }

}
