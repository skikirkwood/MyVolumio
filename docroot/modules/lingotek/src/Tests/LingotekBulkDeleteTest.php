<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Tests\TaxonomyTestTrait;

/**
 * Tests for bulk deletion in the bulk management page.
 *
 * @group lingotek
 */
class LingotekBulkDeleteTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['taxonomy', 'block', 'node'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('page_title_block',['region' => 'header']);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

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

  /**
   * Tests that a node can be deleted in the management page.
   */
  public function testNodeBulkDelete() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create three node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';

    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $edit['title[0][value]'] = 'Llamas are cool 2';
    $edit['body[0][value]'] = 'Llamas are very cool 2';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $edit['title[0][value]'] = 'Llamas should stay';
    $edit['body[0][value]'] = 'Llamas should stay';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->goToContentBulkManagementForm();

    // Ensure the delete operation is there.
    $delete_option = $this->xpath('//*[@id="edit-operation"]/option[text()="Delete content"]');
    $this->assertIdentical(1, count($delete_option), 'Delete operation must be available');

    // Three nodes must be there.
    $this->assertLink('Llamas are cool 2');
    $this->assertLink('Llamas are cool');
    $this->assertLink('Llamas should stay');

    // Mark the first two for deletion.
    $edit = [
      'table[1]' => TRUE,  // Node 1.
      'table[2]' => TRUE,  // Node 2.
      'table[3]' => FALSE,  // Node 3.
      'operation' => 'delete_nodes'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Ensure the confirmation page is shown.
    $this->assertText(t('Are you sure you want to delete these items?'));
    $this->assertText('Llamas are cool');
    $this->assertText('Llamas are cool 2');
    $this->drupalPostForm(NULL, [], t('Delete'));

    // Only one node remains and we are back to the manage page.
    $this->assertText('Deleted 2 posts.');
    $this->assertNoLink('Llamas are cool 2');
    $this->assertNoLink('Llamas are cool');
    $this->assertLink('Llamas should stay');
    $this->assertUrl('admin/lingotek/manage/node');
  }

  /**
   * Tests that a taxonomy term cannot be deleted in the management page.
   */
  public function testTaxonomyTermBulkDelete() {
    $vocabulary = $this->createVocabulary();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $vocabulary->id())->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $vocabulary->id(), TRUE);

    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();
    $bundle = $vocabulary->id();
    $edit = [
      "taxonomy_term[$bundle][enabled]" => 1,
      "taxonomy_term[$bundle][profiles]" => 'automatic',
      "taxonomy_term[$bundle][fields][name]" => 1,
      "taxonomy_term[$bundle][fields][description]" => 1,
    ];

    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    $this->goToContentBulkManagementForm('taxonomy_term');

    // Ensure the delete operation is not there.
    $delete_option = $this->xpath('//*[@id="edit-operation"]/option[text()="Delete content"]');
    $this->assertIdentical(0, count($delete_option), 'Delete operation should not be available');
  }

}
