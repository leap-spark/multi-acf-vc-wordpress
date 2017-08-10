<?php
/**
 * Plugin Name: Multi-Site ACF for Visual Composer
 * Plugin URI: https://github.com/orgs/JUMP-Agency/multi-acf-vc-wordpress
 * Description: Allows you to use shared ACF fields from sub-sites.
 * Version: 1.0.0
 * Author: Aaron Arney
 * Author URI: https://github.com/orgs/JUMP-Agency/
 * License: MIT
 *
 * @package Jump_MU_ACF_Visual_Composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Class Jump_MU_ACF_Visual_Composer
 */
class Jump_MU_ACF_Visual_Composer {

	/**
	 * Jump_MU_ACF_Visual_Composer constructor.
	 *
	 * @since 1.0.0
	 */
	function __construct() {
		// We safely integrate with VC with this hook.
		add_action( 'init', array( $this, 'integrate_with_vc' ) );

		// Use this when creating a shortcode addon.
		add_shortcode( 'jump_acf', array( $this, 'render_field' ) );
	}

	/**
	 * Get Advanced Custom Fields Groups
	 *
	 * @since 1.1.0
	 *
	 * @param null $id A string containing the ID of the groups to grab
	 *
	 * @return array
	 */
	private function get_acf_groups( $id = null ) {
		if ( function_exists( 'acf_get_field_groups' ) ) {
			return acf_get_field_groups( $id );
		} else {
			return apply_filters( 'acf_get_field_groups', array(), $id );
		}
	}

	/**
	 * Get Advanced Custom Fields Fields
	 *
	 * @since 1.1.0
	 *
	 * @param null $id A string containing the ID of the fields to grab
	 *
	 * @return array
	 */
	private function get_acf_fields( $id = null ) {
		if ( function_exists( 'acf_get_fields' ) ) {
			return acf_get_fields( $id );
		} else {
			return apply_filters( 'acf_field_group_get_fields', array(), $id );
		}
	}

	private function get_id_nomenclature( $group ) {
		if ( isset( $group ) ) {
			return 'ID';
		} else {
			return 'id';
		}
	}

	/**
	 * Integrate the plugin with Visual Composer
	 *
	 * TODO: We want to be able to select which blog is the "master" in a dropdown. The other fields will populate their data based on this selection. See [1]
	 *
	 * @since 1.0.0
	 */
	public function integrate_with_vc() {

		if ( ! defined( 'WPB_VC_VERSION' ) ) {
			add_action( 'admin_notices', array( $this, 'show_vc_version_notice' ) );
			return;
		}

		$blog_id_param_values = array();
		$groups_param_elements = array();
		$groups_param_values = array();
		$fields_param_elements = array();
		$fields_param_value = array();
		$fields_params = array();
		$all_sites = get_sites();

		/**
		 * Loop through all of the sites and generate groups and fields for each respective site
		 *
		 * [1] Switch to the current $site context.
		 * [2] Loop through the groups that belong to $site.
		 * [3] Loop through the fields and push them into the $fields array if they are NOT the 'type' set in options
		 * [4] Generate the select/dropdown with the groups assigned to the $site_id.
		 * [5] Generate the select/dropdown with the fields assigned to the $group[ $id ].
		 * [6] Restore the current blog context
		 * [7] Map the elements to Visual Composer
		 *
		 * @since 1.1.0
		 */
		foreach ( $all_sites as $site ) {
			$uuid = uniqid();
			$site_vars = get_object_vars( $site );
			$site_id = $site_vars['blog_id']; // The Blog ID.
			$site_name = get_blog_details( $site_id )->blogname; // Store key name for displaying in VC.
			$blog_id_param_values[ $site_name ] = $site_id; // Store key values for displaying in VC.

			switch_to_blog( (int) $site_id );

			// [2] Loop through the groups that belong to $site.
			$groups = $this->get_acf_groups();

			foreach ( $groups as $group ) {
				$id_nomen = $this->get_id_nomenclature( $group['ID'] );

				// Get all of the groups and assign them to key => values
				// $groups_param_values['Content'] = 23
				// $groups_param_values['Header'] = 28
				$groups_param_values[ $group['title'] ] = $group[ $id_nomen ];


				// echo print_r( $group );
			}



			// Restore the context to the current blog/site.
			restore_current_blog();

		} // End foreach().


		$groups_param_elements[] = array(
			'type'        => 'dropdown',
			'heading'     => __( 'Field group', 'js_composer' ),
			'param_name'  => 'groupasdf',
			'value'       => $groups_param_values,
			'save_always' => true,
			'description' => __( 'Select field group.', 'js_composer' ),
			'dependency'  => array(
				'element' => 'blog_group',
				'value'   => array( $site_id ),
			),
		);

		vc_map( array(
			'name'        => __( 'Multi-Site Advanced Custom Field', 'js_composer' ),
			'base'        => 'jump_acf',
			'icon'        => 'vc_icon-acf',
			'category'    => __( 'Content', 'js_composer' ),
			'description' => __( 'Advanced Custom Field from another blog site', 'js_composer' ),
			'params'      => array_merge( array(
				array(
					'type'        => 'dropdown',
					'heading'     => __( 'Blog', 'js_composer' ),
					'param_name'  => 'blog_group',
					'value'       => $blog_id_param_values,
					'save_always' => true,
					'description' => __( 'Select blog.', 'js_composer' ),
				),
			), $groups_param_elements, array(
				array(
					'type'        => 'textfield',
					'heading'     => __( 'Extra class name', 'js_composer' ),
					'param_name'  => 'el_class',
					'description' => __( 'Style particular content element differently - add a class name and refer to it in custom CSS.', 'js_composer' ),
				),
			) ),
		) );
	}

	/**
	 * Shortcode logic how it should be rendered.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts An array of attribute values.
	 * @param null  $content The content inside of the shortcode.
	 *
	 * @return string
	 */
	public function render_field( $atts, $content = null ) {
		$field_key = '';

		/**
		 * Extract the shortcode attributes.
		 *
		 * @since 1.0.0
		 *
		 * @var string $el_class
		 * @var string $show_label
		 * @var string $align
		 * @var string $field_group
		 */
		$shortcode_atts = shortcode_atts( array(
			'el_class'    => '',
			'field_group' => '',
		), $atts );

		// Switch the context to the 'master' blog where the desired fields live.
		switch_to_blog( 1 );

		if ( 0 === strlen( $field_group ) ) {

			$groups = $this->get_acf_groups();

			if ( is_array( $groups ) && isset( $groups[0] ) ) {
				$key = 'id';

				if ( isset( $groups[0]['ID'] ) ) {
					$key = 'ID';
				}

				$field_group = $groups[0][ $key ];
			}
		}

		if ( ! empty( $field_group ) ) {

			if ( ! empty( $shortcode_atts[ 'field_from_' . $field_group ] ) ) {
				$field_key = $shortcode_atts[ 'field_from_' . $field_group ];
			} else {
				$field_key = 'field_from_group_' . $field_group;
			}
		}

		$field = get_field_object( $field_key, 'option' );

		// Restore the context to the current blog/site.
		restore_current_blog();

		return '<div>' . $field['value'] . '</div>';
	}

	/**
	 * Show notice if VC is not installed.
	 *
	 * @since 1.0.0
	 */
	public function show_vc_version_notice() {
		$plugin_data = get_plugin_data( __FILE__ );

		/* translators: Notice to install missing Visual Composer plugin */
		echo '<div class="updated"><p>' . esc_html( sprintf( __( '<strong>%s</strong> requires <strong><a href="http://bit.ly/vcomposer" target="_blank">Visual Composer</a></strong> plugin to be installed and activated on your site.', 'vc_extend' ), $plugin_data['Name'] ) ) . '</p>
        </div>';
	}
}

new Jump_MU_ACF_Visual_Composer();
