<?php
/**
 * This file implements a class derived of the generic Skin class in order to provide custom code for
 * the skin in this folder.
 *
 * This file is part of the b2evolution project - {@link http://b2evolution.net/}
 *
 * @package skins
 * @subpackage evopress
 *
 * @version $Id: _skin.class.php 9 2011-10-24 22:32:00Z fplanque $
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Specific code for this skin.
 *
 * ATTENTION: if you make a new skin you have to change the class name below accordingly
 */
class evopress_Skin extends Skin
{
  /**
	 * Get default name for the skin.
	 * Note: the admin can customize it.
	 */
	function get_default_name()
	{
		return 'evoPress';
	}


  /**
	 * Get default type for the skin.
	 */
	function get_default_type()
	{
		return 'normal';
	}


	/**
   * Get definitions for editable params
   *
	 * @see Plugin::GetDefaultSettings()
	 * @param local params like 'for_editing' => true
	 */
	function get_param_definitions( $params )
	{
		$r = array_merge( array(
				'display_post_date' => array(
					'label' => T_('Post date'),
					'note' => T_('Display the date of each post'),
					'defaultvalue' => 1,
					'type' => 'checkbox',
				),
				'sidebar_position' => array(
					'label' => T_('Sidebar position'),
					'note' => '',
					'defaultvalue' => 'right',
					'options' => array( 'left' => $this->T_('Left'), 'right' => $this->T_('Right') ),
					'type' => 'select',
				),
				'colorbox' => array(
					'label' => T_('Colorbox Image Zoom'),
					'note' => T_('Check to enable javascript zooming on images (using the colorbox script)'),
					'defaultvalue' => 1,
					'type'	=>	'checkbox',
				),							
			), parent::get_param_definitions( $params )	);

		return $r;
	}


	/**
	 * Get ready for displaying the skin.
	 *
	 * This may register some CSS or JS...
	 */
	function display_init()
	{
		// call parent:
		parent::display_init();

		// Add CSS:
		// fp> Note: having those here should allow
		// 1) Requesting them earlier as if they are @import'ed
		// 2) Allow bundling
		// fp> I am not 100% sure though. Comments welcome :)
		require_css( 'basic.css' );
		require_css( 'basic_styles.css', false, NULL, NULL, '' );	// Do not include v= for now or else that css will be loaded twice due to @import
		require_css( 'blog_base.css' );
		require_css( 'item_base.css', false, NULL, NULL, '' );	// Do not include v= for now or else that css will be loaded twice due to @import
		require_css( 'item.css', true );
		require_css( 'style.css', true );

		// Colorbox (a lightweight Lightbox alternative) allows to zoom on images and do slideshows with groups of images:
		if( $this->get_setting("colorbox") ) 
		{
			require_js_helper( 'colorbox' );
		}
	}

}

/*
 * $Log: _skin.class.php,v $
 */
?>