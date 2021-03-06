<?php

namespace Drupal\Tests\commerce_store\Functional;

use Drupal\commerce_store\Entity\StoreType;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\simpletest\BlockCreationTrait;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Ensure the store type works correctly.
 *
 * @group commerce
 */
class StoreTypeTest extends CommerceBrowserTestBase {

  use BlockCreationTrait;
  use StoreCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['block', 'commerce_store'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->placeBlock('local_tasks_block');
    $this->placeBlock('page_title_block');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer store types',
      'administer stores',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests if the default Store Type was created.
   */
  public function testDefaultStoreType() {
    $this->drupalGet('admin/commerce/config/store-types');
    $store_types = StoreType::loadMultiple();

    $this->assertTrue(isset($store_types['default']), 'The default store type is available');

    $store_type = StoreType::load('default');
    $this->assertEquals($store_type, $store_types['default'], 'The correct store type is loaded');
  }

  /**
   * Tests if the correct number of Store Types are being listed.
   */
  public function testListStoreType() {
    $title = strtolower($this->randomMachineName(8));
    $table_selector = '//table/tbody/tr';

    // The store shows one default store type.
    $this->drupalGet('admin/commerce/config/store-types');
    $store_types = $this->getSession()->getDriver()->find($table_selector);
    $this->assertEquals(1, count($store_types), 'Stores types are correctly listed');

    // Create a new commerce store type and see if the list has two store types.
    $this->createEntity('commerce_store_type', [
      'id' => $title,
      'label' => $title,
    ]);

    $this->drupalGet('admin/commerce/config/store-types');
    $store_types = $this->getSession()->getDriver()->find($table_selector);
    $this->assertEquals(2, count($store_types), 'Stores types are correctly listed');
    $this->saveHtmlOutput();
  }

  /**
   * Tests creating a Store Type programaticaly and through the create form.
   */
  public function testCreateStoreType() {
    $title = strtolower($this->randomMachineName(8));

    // Create a store type programmaticaly.
    $type = $this->createEntity('commerce_store_type', [
      'id' => $title,
      'label' => $title,
    ]);
    $type_exists = (bool) StoreType::load($type->id());
    $this->assertTrue($type_exists, 'The new store type has been created in the database.');

    // Create a store type through the form.
    $this->drupalGet('admin/commerce/config/store-types/add');
    $edit = [
      'id' => 'foo',
      'label' => 'Label of foo',
    ];
    $this->submitForm($edit, 'Save');
    $type_exists = (bool) StoreType::load($edit['id']);
    $this->assertTrue($type_exists, 'The new store type has been created in the database.');
  }

  /**
   * Tests updating a Store Type through the edit form.
   */
  public function testUpdateStoreType() {
    // Create a new store type.
    $store_type = $this->createEntity('commerce_store_type', [
      'id' => 'foo',
      'label' => 'Label for foo',
    ]);

    $this->drupalGet('admin/commerce/config/store-types/default/edit');

    // Only change the label.
    $edit = [
      'label' => $this->randomMachineName(8),
    ];
    $this->submitForm($edit, 'Save');
    $changed = StoreType::load($store_type->id());
    $this->assertEquals($changed->label(), $store_type->label(), 'The label of the store type has been changed.');
  }

  /**
   * Tests deleting a Store Type through the form.
   */
  public function testDeleteStoreType() {
    // Create a store type programmaticaly.
    $type = $this->createEntity('commerce_store_type', [
      'id' => 'foo',
      'label' => 'Label for foo',
    ]);

    // Create a store.
    $store = $this->createStore(NULL, NULL, $type->id());

    // Try to delete the store type.
    $this->drupalGet('admin/commerce/config/store-types/' . $type->id() . '/delete');
    $this->assertSession()->pageTextContains(t('@type is used by 1 store on your site. You can not remove this store type until you have removed all of the @type stores.', ['@type' => $type->label()]));
    $this->assertSession()->pageTextNotContains('This action cannot be undone.');
    $this->assertSession()->pageTextNotContains('The store type deletion confirmation form is not available');

    // Deleting the store type when its not being referenced by a store.
    $store->delete();
    $this->drupalGet('admin/commerce/config/store-types/' . $type->id() . '/delete');
    $this->assertSession()->pageTextContains(t('Are you sure you want to delete the store type @type?', ['@type' => $type->label()]));
    $this->saveHtmlOutput();
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->submitForm([], 'Delete');
    $type_exists = (bool) StoreType::load($type->id());
    $this->assertFalse($type_exists, 'The new store type has been deleted from the database.');
  }

}
