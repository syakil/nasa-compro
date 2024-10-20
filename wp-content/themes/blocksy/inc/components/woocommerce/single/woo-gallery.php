<?php

add_action(
	'woocommerce_before_template_part',
	function ($template_name, $template_path, $located, $args) {
		if ($template_name !== 'single-product/product-image.php') {
			return;
		}

		if (! blocksy_woocommerce_has_flexy_view()) {
			return;
		}

		echo blocksy_render_view(dirname(__FILE__) . '/woo-gallery-template.php');

		ob_start();
	},
	4, 4
);

add_action(
	'woocommerce_after_template_part',
	function ($template_name, $template_path, $located, $args) {
		if ($template_name !== 'single-product/product-image.php') {
			return;
		}

		if (! blocksy_woocommerce_has_flexy_view()) {
			return;
		}

		ob_get_clean();
	},
	4, 4
);


add_filter(
	'blocksy:woocommerce:single-product:post-class',
	function($classes) {
		if (! blocksy_manager()->screen->is_product()) {
			return $classes;
		}

		global $blocksy_is_quick_view;
		global $product;

		if (
			! $blocksy_is_quick_view
			&&
			// Integration with Custom Product Boxes plugin
			$product->get_type() !== 'wdm_bundle_product'
		) {
			$classes[] = 'ct-default-gallery';
		}
	
		return $classes;
	}
);

add_filter(
	'woocommerce_post_class',
	'blocksy_woo_single_post_class',
	999,
	2
);

function blocksy_woo_single_post_class($classes, $product) {
	if (! blocksy_manager()->screen->is_product()) {
		return $classes;
	}

	if (blocksy_woocommerce_has_flexy_view()) {
		$has_gallery = count($product->get_gallery_image_ids()) > 0;

		if ($product->get_type() === 'variable') {
			$maybe_current_variation = blocksy_retrieve_product_default_variation(
				$product
			);

			if ($maybe_current_variation) {
				$post_id = $maybe_current_variation->get_id();

				global $sitepress, $woocommerce_wpml;

				if (
					$sitepress
					&&
					$woocommerce_wpml
				) {
					$post_id = apply_filters('wpml_object_id', $maybe_current_variation->get_id(), 'product_variation', TRUE, $sitepress->get_default_language());
				}

				$variation_values = get_post_meta($post_id, 'blocksy_post_meta_options');

				if (empty($variation_values)) {
					$variation_values = [[]];
				}

				$variation_values = $variation_values[0];

				$gallery_source = blocksy_akg(
					'gallery_source',
					$variation_values,
					'default'
				);

				if ($gallery_source !== 'default') {
					$has_gallery = count(blocksy_akg(
						'images',
						$variation_values,
						[]
					)) > 0;
				}
			}
		}

		if ($has_gallery) {
			if (blocksy_get_theme_mod('gallery_style', 'horizontal') === 'vertical') {
				$classes[] = 'thumbs-left';
			} else {
				$classes[] = 'thumbs-bottom';
			}
		}
	}

	$product_view_type = blocksy_get_product_view_type();

	if (
		$product_view_type === 'default-gallery'
		||
		$product_view_type === 'stacked-gallery'
	) {
		if (blocksy_get_theme_mod('has_product_sticky_gallery', 'no') === 'yes') {
			$classes[] = 'sticky-gallery';
		}

		if (blocksy_get_theme_mod('has_product_sticky_summary', 'no') === 'yes') {
			$classes[] = 'sticky-summary';
		}
	}

	return $classes;
}

function blocksy_retrieve_product_default_variation($product, $object = true) {
	$should_use_ajax_variations = (
		count($product->get_children()) > apply_filters(
			'woocommerce_ajax_variation_threshold',
			30,
			$product
		)
	);

	if ($should_use_ajax_variations) {
		return null;
	}

	$maybe_variation = null;

	$default_attributes = $product->get_default_attributes();

	if (count($default_attributes) === count($product->get_variation_attributes())) {
		$prefixed_slugs = array_map(function($pa_name) {
			return 'attribute_'. sanitize_title($pa_name);
		}, array_keys($default_attributes));

		$default_attributes = array_combine($prefixed_slugs, $default_attributes);

		$maybe_variation = (new \WC_Product_Data_Store_CPT())->find_matching_product_variation(
			$product,
			$default_attributes
		);
	}

	$has_some_matching_get_param = false;

	foreach ($product->get_variation_attributes() as $attribute_name => $attribute_values) {
		if (isset($_GET['attribute_' . $attribute_name])) {
			$has_some_matching_get_param = true;
			break;
		}
	}

	if ($has_some_matching_get_param) {
		$maybe_get_variation = (new \WC_Product_Data_Store_CPT())->find_matching_product_variation(
			$product,
			$_GET
		);

		if ($maybe_get_variation) {
			$maybe_variation = $maybe_get_variation;
		}
	}

	$current_variation = null;

	if ($maybe_variation) {
		$current_variation = wc_get_product($maybe_variation);
	}

	if (! $object && $current_variation) {
		return $current_variation->get_id();
	}

	return $current_variation;
}

function blocksy_get_product_view_type() {
	return apply_filters(
		'blocksy:woocommerce:product-single:view-type',
		'default-gallery'
	);
}

