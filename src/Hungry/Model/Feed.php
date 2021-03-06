<?php

/**
 * Made with ❤ by themesfor.me
 *
 * XML Feed generation
 */

namespace Hungry\Model;

use Hungry\Tool\XML;
use Hungry\Tool\Template;

class Feed
{
	// Constraints
	const MAX_PRODUCTS = 5000;
	private $type;

	public function __construct($type)
	{
		$this->type = $type;
	}

	/**
     * Generate XML ready for Google Merchants
     */
	public function getXML()
	{
			
		if ($this->type == "pricecheck") {
			$template = new Template(\Hungry\__ASSETS__ . '/pc-feed.xml');
		}
		else{
			$template = new Template(\Hungry\__ASSETS__ . '/google-feed.xml');
		}

		$template->title = get_bloginfo('name');
		$template->link = get_bloginfo('url');
		$template->description = get_bloginfo('description');
		$template->items = $this->create_items_list();

		$xml = $template->render();
		$xml = XML::remove_empty_nodes($xml);

		return $xml;
	}

	/**
     * Create items specific XML
     *
     * @return string xml string with the items filled in
     */
	public function create_items_list()
	{
		$args = array('post_type' => 'product','posts_per_page' => self::MAX_PRODUCTS);
	  	$query = new \WP_Query($args);
		$itemsXML = '';
		
		if($query->have_posts()) {

			$posts = $query->get_posts();
			foreach($posts as $post) {
				$tfm_product = new Product($post, $this->type);
				$itemsXML .= $tfm_product->get_xml();
			}
		}
		
		return $itemsXML;
	}
}
