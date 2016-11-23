<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\pathauto\Tests\PathautoTestHelperTrait;

/**
 * Tests translating a node that contains a path.
 *
 * @group lingotek
 */
class LingotekNodeWithPathautoTranslationTest extends LingotekTestBase {

  use EntityReferenceTestTrait;
  use PathautoTestHelperTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'path', 'pathauto'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));

    // Add locales.
    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish',
      'native' => 'Español',
      'direction' => '',
    ];
    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);

    $post = [
      'code' => 'es_AR',
      'language' => 'Spanish',
      'native' => 'Español',
      'direction' => '',
    ];
    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    // Create pathauto config.
    $this->createPattern('node', '/node/[node:title]')->save();

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
      'node[article][fields][path]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithPathautoTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+path');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['path'][0]));
    $this->assertEqual($data['path'][0]['alias'], '/node/llamas-are-cool');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/llamas-are-cool'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/llamas-are-cool');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLinkHelper(t('Request translation'), 0,  '//a[normalize-space()=:label and contains(@href,\'es_AR\')]');
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLinkByHref('/admin/lingotek/workbench/dummy-document-hash-id/es');
    $url = Url::fromRoute('lingotek.workbench', array('doc_id' => 'dummy-document-hash-id', 'locale' => 'es_AR'), array('language' => ConfigurableLanguage::load('es-ar')))->toString();
    $this->assertRaw('<a href="' . $url .'" target="_blank" hreflang="es-ar">');
    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
    $this->assertUrl(\Drupal::request()->getUriForPath('/es-ar/node/las-llamas-son-chulas'));
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithEmptyPathTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+nullpath');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['path[0][pathauto]'] = FALSE;
    $edit['path[0][alias]'] = '';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    // The pathauto will be there because have been created by pathauto.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertFalse(isset($data['path']));

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLinkHelper(t('Request translation'), 0,  '//a[normalize-space()=:label and contains(@href,\'es_AR\')]');
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLinkByHref('/admin/lingotek/workbench/dummy-document-hash-id/es');
    $url = Url::fromRoute('lingotek.workbench', array('doc_id' => 'dummy-document-hash-id', 'locale' => 'es_AR'), array('language' => ConfigurableLanguage::load('es-ar')))->toString();
    $this->assertRaw('<a href="' . $url .'" target="_blank" hreflang="es-ar">');
    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
    $this->assertUrl(\Drupal::request()->getUriForPath('/es-ar/node/1'));
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithPathautoTranslationAndUntranslatedAlias() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+path');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['path[0][pathauto]'] = FALSE;
    $edit['path[0][alias]'] = '/llamas-are-cool';

    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['path'][0]));
    $this->assertEqual($data['path'][0]['alias'], '/llamas-are-cool');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/llamas-are-cool'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('/llamas-are-cool');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLinkHelper(t('Request translation'), 0,  '//a[normalize-space()=:label and contains(@href,\'es_AR\')]');
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLinkByHref('/admin/lingotek/workbench/dummy-document-hash-id/es');
    $url = Url::fromRoute('lingotek.workbench', array('doc_id' => 'dummy-document-hash-id', 'locale' => 'es_AR'), array('language' => ConfigurableLanguage::load('es-ar')))->toString();
    $this->assertRaw('<a href="' . $url .'" target="_blank" hreflang="es-ar">');
    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
    $this->assertUrl(\Drupal::request()->getUriForPath('/es-ar/las-llamas-son-chulas'));
  }

}
