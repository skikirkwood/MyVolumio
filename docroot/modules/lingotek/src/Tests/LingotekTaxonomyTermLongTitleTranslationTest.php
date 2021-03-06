<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Tests\TaxonomyTestTrait;

/**
 * Tests translating a taxonomy term with a very long title that doesn't fit.
 *
 * @group lingotek
 */
class LingotekTaxonomyTermLongTitleTranslationTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'taxonomy'];

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The term that should be translated.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $term;

  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $this->vocabulary = $this->createVocabulary();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $this->vocabulary->id())->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $bundle = $this->vocabulary->id();
    $edit = [
      "taxonomy_term[$bundle][enabled]" => 1,
      "taxonomy_term[$bundle][profiles]" => 'automatic',
      "taxonomy_term[$bundle][fields][name]" => 1,
      "taxonomy_term[$bundle][fields][description]" => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'taxonomy_term_long_title');
  }

  /**
   * Tests that a term can be translated.
   */
  public function testTermTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);
    $bundle = $this->vocabulary->id();

    // Create a term.
    $edit = array();
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->term = Term::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));

    // Check that the translate tab is in the node.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation. It must fail with a useful error message.
    $this->clickLink('Download completed translation');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation value: name.');
  }

  /**
   * Tests that a term can be translated when created via API with automated upload.
   */
  public function testTermTranslationViaAPIWithAutomatedUpload() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a term.
    $this->term = Term::create([
      'name' => 'Llamas are cool',
      'description' => 'Llamas are very cool',
      'langcode' => 'en',
      'vid' => $this->vocabulary->id(),
    ]);
    $this->term->save();

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));

    // Check that the translate tab is in the node.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation. It must fail with a useful error message.
    $this->clickLink('Download completed translation');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation value: name.');
  }

  /**
   * Tests that a term can be translated when created via API with automated upload.
   */
  public function testTermTranslationViaAPIWithManualUpload() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $bundle = $this->vocabulary->id();
    $edit = [
      "taxonomy_term[$bundle][enabled]" => 1,
      "taxonomy_term[$bundle][profiles]" => 'manual',
      "taxonomy_term[$bundle][fields][name]" => 1,
      "taxonomy_term[$bundle][fields][description]" => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // Create a term.
    $this->term = Term::create([
      'name' => 'Llamas are cool',
      'description' => 'Llamas are very cool',
      'langcode' => 'en',
      'vid' => $this->vocabulary->id(),
    ]);
    $this->term->save();

    // Check that the translate tab is in the node.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should not have been automatically uploaded, so let's upload it.
    $this->clickLink('Upload');
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation. It must fail with a useful error message.
    $this->clickLink('Download completed translation');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation value: name.');
  }

  /**
   * Tests that a taxonomy term can be translated using the links on the management page.
   */
  public function testBulkTermTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $bundle = $this->vocabulary->id();
    $edit = [
      "taxonomy_term[$bundle][enabled]" => 1,
      "taxonomy_term[$bundle][profiles]" => 'manual',
      "taxonomy_term[$bundle][fields][name]" => 1,
      "taxonomy_term[$bundle][fields][description]" => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // Create a term.
    $edit = array();
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->goToContentBulkManagementForm('taxonomy_term');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/taxonomy_term/1?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_ES?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $this->clickLink('EN');
    $this->assertText('Taxonomy_term Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_ES?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $this->clickLink('EN');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_ES?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");
    $this->assertIdentical('es_ES', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_ES?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $this->clickLink('ES');
    $this->assertIdentical('es_ES', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation. It must fail with a useful error message.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_ES?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $this->clickLink('ES');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation value: name.');
  }

  /**
   * Tests that a taxonomy_term can be translated using the actions on the management page.
   */
  public function testBulkTermTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    $bundle = $this->vocabulary->id();
    $edit = [
      "taxonomy_term[$bundle][enabled]" => 1,
      "taxonomy_term[$bundle][profiles]" => 'manual',
      "taxonomy_term[$bundle][fields][name]" => 1,
      "taxonomy_term[$bundle][fields][description]" => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // Create a term.
    $edit = array();
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->goToContentBulkManagementForm('taxonomy_term');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/taxonomy_term/1?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $edit = [
      'table[1]' => TRUE,  // Taxonomy_term 1.
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $edit = [
      'table[1]' => TRUE,  // Taxonomy_term 1.
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $edit = [
      'table[1]' => TRUE,  // Taxonomy_term 1.
      'operation' => 'request_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/de_AT?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $edit = [
      'table[1]' => TRUE,  // Taxonomy_term 1.
      'operation' => 'check_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath .'/admin/lingotek/manage/taxonomy_term');
    $edit = [
      'table[1]' => TRUE,  // Taxonomy_term 1.
      'operation' => 'download:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Download translation. It must fail with a useful error message.
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation value: name.');
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));
  }

}