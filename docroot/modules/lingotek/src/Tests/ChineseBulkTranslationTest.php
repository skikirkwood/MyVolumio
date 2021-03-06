<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests translating into chinese locales.
 *
 * @group lingotek
 */
class ChineseBulkTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'comment'];

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));

    // Add locale.
    $post = [
      'code' => 'zh_CN',
      'language' => 'Chinese',
      'native' => 'Chinese',
      'direction' => '',
    ];
    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);

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
      'node[article][profiles]' => 'manual',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'manual',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testNodeTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath .'/admin/lingotek/manage/node');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/zh_CN?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('EN');

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath .'/admin/lingotek/manage/node');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/zh_CN?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/zh_CN?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('ZH');
    $this->assertText("Locale 'zh_CN' was added as a translation target for node Llamas are cool.");
    // Check that the requested locale is the right one.
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/zh_CN?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('ZH');
    $this->assertText('The zh_CN translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/zh_CN?destination=' . $basepath .'/admin/lingotek/manage/node');
    $this->clickLink('ZH');
    $this->assertText('The translation of node Llamas are cool into zh_CN has been downloaded.');

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/zh_CN');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/zh_CN' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testSystemSiteTranslationUsingLinks() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');

    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    // Request the Chinese translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertText("Translation to zh_CN requested successfully");
    // Check that the requested locale is the right one.
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Chinese translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to zh_CN checked successfully");

    // Download the Chinese translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertText('Translation to zh_CN downloaded successfully');
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/zh_CN');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/zh_CN' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testContentTypeTranslationUsingLinks() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText('Article status checked successfully');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/node_type/article/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertText("Translation to zh_CN requested successfully");
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/node_type/article/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to zh_CN status checked successfully");

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/node_type/article/zh_CN?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ZH');
    $this->assertText('Translation to zh_CN downloaded successfully');
    $this->assertIdentical('zh_CN', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/zh_CN');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/zh_CN' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
  }


}
