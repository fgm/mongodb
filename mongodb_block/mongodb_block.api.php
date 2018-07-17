<?php

/**
 * @file
 * Hooks provided by the Block module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Define all blocks provided by the module.
 *
 * This hook declares to Drupal what blocks are provided by your module and can
 * optionally specify initial block configuration settings.
 *
 * @return array
 *   An associative array whose keys define the delta for each block and whose
 *   values contain the block descriptions. Each block description is itself an
 *   associative array, with the following key-value pairs:
 *   - cache: (optional) A bitmask describing what kind of caching is
 *     appropriate for the block. Drupal provides the following bitmask
 *     constants for defining cache granularity:
 *     - DRUPAL_CACHE_PER_ROLE (default): The block can change depending on the
 *       roles the user viewing the page belongs to.
 *     - DRUPAL_CACHE_PER_USER: The block can change depending on the user
 *       viewing the page. This setting can be resource-consuming for sites with
 *       large number of users, and should only be used when
 *       DRUPAL_CACHE_PER_ROLE is not sufficient.
 *     - DRUPAL_CACHE_PER_PAGE: The block can change depending on the page being
 *       viewed.
 *     - DRUPAL_CACHE_GLOBAL: The block is the same for every user on every page
 *       where it is visible.
 *     - DRUPAL_CACHE_CUSTOM: The module implements its own caching system.
 *     - DRUPAL_NO_CACHE: The block should not get cached.
 *   - weight: (optional) Initial value for the ordering weight of this block.
 *     Most modules do not provide an initial value, and any value provided can
 *     be modified by a user on the block configuration screen.
 *   - status: (optional) Initial value for block enabled status. (1 = enabled,
 *     0 = disabled). Most modules do not provide an initial value, and any
 *     value provided can be modified by a user on the block configuration
 *     screen.
 *   - region: (optional) Initial value for theme region within which this
 *     block is set. Most modules do not provide an initial value, and any value
 *     provided can be modified by a user on the block configuration screen.
 *     Note: If you set a region that isn't available in the currently enabled
 *     theme, the block will be disabled.
 *   - 'pages': an array of menu paths where the block should appear.
 *     Example: array('admin', 'node/%/edit') will display the block on admin,
 *     admin/structure etc. and the node edit pages.
 *   - 'node_type': List of node types. The block will appear only on node/%
 *     pages with the specified node types.
 */
function hook_block_info() {
  $blocks['exciting'] = array(
    'info' => t('An exciting block provided by Mymodule.'),
    'weight' => 0,
    'status' => 1,
    'region' => 'sidebar_first',
    // DRUPAL_CACHE_PER_ROLE will be assumed for block 0.
  );

  $blocks['amazing'] = array(
    'info' => t('An amazing block provided by Mymodule.'),
    'cache' => DRUPAL_CACHE_PER_ROLE | DRUPAL_CACHE_PER_PAGE,
  );

  return $blocks;
}

/**
 * Alters the information collected in hook_block_info.
 *
 * The core hook_block_info_alter is run-time, not supported. This is build
 * time.
 *
 * @param array $blocks
 *   Associative array, key is the module name, value is the hook_block_info
 *   returned by the module. As Drupal core and contrib rarely sets status
 *   and region this hook should be used to enable those.
 */
function hook_mongodb_block_info_alter(array &$blocks) {
  // Enable the management block.
  $blocks['system']['management']['status'] = 1;
  $blocks['system']['management']['region'] = 'sidebar_first';
}

/**
 * Return a rendered or renderable view of a block.
 *
 * The functions mymodule_display_block_exciting and _amazing, as used in the
 * example, should of course be defined somewhere in your module and return the
 * content you want to display to your users. If the "content" element is empty,
 * no block will be displayed even if "subject" is present.
 *
 * For a detailed usage example, see block_example.module.
 *
 * @param string $delta
 *   Which block to render. This is a unique identifier for the block
 *   within the module, defined in hook_block_info().
 *
 * @return array
 *   Either an empty array so the block will not be shown or an array containing
 *   the following elements:
 *   - subject: The default localized title of the block. If the block does not
 *     have a default title, this should be set to NULL.
 *   - content: The content of the block's body. This may be a renderable array
 *     (preferable) or a string containing rendered HTML content. If the content
 *     is empty the block will not be shown.
 *
 * @see hook_block_info()
 * @see hook_block_view_alter()
 * @see hook_block_view_MODULE_DELTA_alter()
 */
function hook_block_view($delta = '') {
  switch ($delta) {
    case 'exciting':
      $block = array(
        'subject' => t('Default title of the exciting block'),
        'content' => mymodule_display_block_exciting(),
      );
      break;

    case 'amazing':
      $block = array(
        'subject' => t('Default title of the amazing block'),
        'content' => mymodule_display_block_amazing(),
      );
      break;
  }
  return $block;
}

/**
 * Perform alterations to the content of a block.
 *
 * This hook allows you to modify any data returned by hook_block_view().
 *
 * Note that instead of hook_block_view_alter(), which is called for all
 * blocks, you can also use hook_block_view_MODULE_DELTA_alter() to alter a
 * specific block.
 *
 * @param array $data
 *   The data as returned from the hook_block_view() implementation of the
 *   module that defined the block. This could be an empty array or NULL value
 *   (if the block is empty) or an array containing:
 *   - subject: The default localized title of the block.
 *   - content: Either a string or a renderable array representing the content
 *     of the block. You should check that the content is an array before trying
 *     to modify parts of the renderable structure.
 * @param object $block
 *   The block object, as loaded from the database, having the main properties:
 *   - module: The name of the module that defined the block.
 *   - delta: The unique identifier for the block within that module, as defined
 *     in hook_block_info().
 *
 * @see hook_block_view_alter()
 * @see hook_block_view()
 */
function hook_block_view_alter(array &$data, $block) {
  // Remove the contextual links on all blocks that provide them.
  if (is_array($data['content']) && isset($data['content']['#contextual_links'])) {
    unset($data['content']['#contextual_links']);
  }
  // Add a theme wrapper function defined by the current module to all blocks
  // provided by the "somemodule" module.
  if (is_array($data['content']) && $block->module == 'somemodule') {
    $data['content']['#theme_wrappers'][] = 'mymodule_special_block';
  }
}

/**
 * Perform alterations to a specific block.
 *
 * Modules can implement hook_block_view_MODULE_DELTA_alter() to modify a
 * specific block, rather than implementing hook_block_view_alter().
 *
 * Note that this hook fires before hook_block_view_alter(). Therefore, all
 * implementations of hook_block_view_MODULE_DELTA_alter() will run before all
 * implementations of hook_block_view_alter(), regardless of the module order.
 *
 * @param array $data
 *   The data as returned from the hook_block_view() implementation of the
 *   module that defined the block. This could be an empty array or NULL value
 *   (if the block is empty) or an array containing:
 *   - subject: The localized title of the block.
 *   - content: Either a string or a renderable array representing the content
 *     of the block. You should check that the content is an array before trying
 *     to modify parts of the renderable structure.
 * @param object $block
 *   The block object, as loaded from the database, having the main properties:
 *   - module: The name of the module that defined the block.
 *   - delta: The unique identifier for the block within that module, as defined
 *     in hook_block_info().
 *
 * @see hook_block_view_alter()
 * @see hook_block_view()
 */
function hook_block_view_MODULE_DELTA_alter(array &$data, $block) {
  // This code will only run for a specific block. For example, if MODULE_DELTA
  // in the function definition above is set to "mymodule_somedelta", the code
  // will only run on the "somedelta" block provided by the "mymodule" module.
  // Change the title of the "somedelta" block provided by the "mymodule"
  // module.
  $data['subject'] = t('New title of the block');
}

/**
 * @} End of "addtogroup hooks".
 */
