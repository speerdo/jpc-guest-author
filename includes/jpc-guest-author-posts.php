<?php
function jpc_guest_author_posts()
{
	// set up labels
	$labels = array(
		'name' => 'JPC Guest Authors',
		'singular_name' => 'JPC Guest Author',
		'add_new' => 'Add New JPC Guest Author',
		'add_new_item' => 'Add New JPC Guest Author',
		'edit_item' => 'Edit JPC Guest Author',
		'new_item' => 'New JPC Guest Author',
		'all_items' => 'All JPC Guest Authors',
		'view_item' => 'View JPC Guest Author',
		'search_items' => 'Search JPC Guest Authors',
		'not_found' => 'No JPC Guest Authors Found',
		'not_found_in_trash' => 'No JPC Guest Authors found in Trash',
		'parent_item_colon' => '',
		'menu_name' => 'JPC Guest Authors',
	);
	//register post type
	register_post_type(
		'jpc_guest_author',
		array(
			'labels' => $labels,
			'has_archive' => true,
			'public' => true,
			'menu_icon' => 'dashicons-businessman',
			'supports' => array('title', 'editor', 'thumbnail'),
			'taxonomies' => array('post_tag', 'category'),
			'exclude_from_search' => false,
			'capability_type' => 'post',
			'rewrite' => array('slug' => 'authors'),
		)
	);
}
add_action('init', 'jpc_guest_author_posts');

//hook into the init action and call create_topics_nonhierarchical_taxonomy when it fires
add_action('init', 'create_topics_nonhierarchical_taxonomy', 0);

function create_topics_nonhierarchical_taxonomy()
{
	// Labels part for the GUI
	$labels = array(
		'name' => _x('Authors', 'taxonomy general name'),
		'singular_name' => _x('Author Tag', 'taxonomy singular name'),
		'search_items' => __('Search Author Tags'),
		'popular_items' => __('Popular Author  Tags'),
		'all_items' => __('All Author  Tags'),
		'parent_item' => null,
		'parent_item_colon' => null,
		'edit_item' => __('Edit Author Tag'),
		'update_item' => __('Update Author Tag'),
		'add_new_item' => __('Add New Author Tag'),
		'new_item_name' => __('New Author Tag Name'),
		'separate_items_with_commas' => __('Separate Author Tag with commas'),
		'add_or_remove_items' => __('Add or remove Author Tag'),
		'choose_from_most_used' => __('Choose from the most used Author Tag'),
		'menu_name' => __('Authors Tags'),
	);

	// Now register the non-hierarchical taxonomy like tag
	register_taxonomy('jpc_guest_author_tags', array('post', 'jpc_guest_author'), array(
		'hierarchical' => false,
		'labels' => $labels,
		'show_ui' => true,
		'show_admin_column' => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var' => true,
		'rewrite' => array('slug' => 'authors'),
	));
}

function save_author_tag($post_id, $post, $update)
{
	$posttype = 'jpc_guest_author';

	if ($posttype != $post->post_type) {
		return;
	}

	if (isset($_REQUEST['post_title'])) {
		$authortag = $_POST['post_title'];
		$authorslug = str_replace(' ', '-', strtolower($_POST['post_title']));
		wp_insert_term($authortag, 'jpc_guest_author_tags', array('slug' => $authorslug));
	}
}
add_action('save_post', 'save_author_tag', 10, 3);

add_filter('the_author', 'guest_author_name');
add_filter('get_the_author_display_name', 'guest_author_name');

function guest_author_name($name)
{
	global $post;
	$authors = wp_get_post_terms($post->ID, 'jpc_guest_author_tags');

	$author = "";
	if (count($authors) > 0) {
		foreach ($authors as $a) {
			$author .= '<a href="' . get_term_link($a) . '">' . $a->name . '</a> ';
		}
		// Remove trailing space
		$author = rtrim($author);
	}

	if ($author > "")
		$name = $author;

	return $name;
}

add_filter('the_author_link', 'guest_author_link_filter');
function guest_author_link_filter($content)
{
	// No need to print the content, just return it
	return $content;
}

/**
 * Add styling for author links and add bullet separator
 * Only on single post/page views
 */
add_action('wp_head', 'jpc_author_style_single');
function jpc_author_style_single()
{
	// Only apply on single post/page views
	if (!is_single() && !is_page()) {
		return;
	}
	?>
	<style type="text/css">
		/* Make author links blue on single post pages */
		.td-post-header .td-post-author-name a,
		.meta-info a:not(.td-post-small-box a) {
			color: #4db2ec !important;
		}

		/* Keep original styling for source links */
		.td-post-small-box a {
			color: inherit !important;
		}

		/* Hide the default dash separator */
		.td-post-header .td-post-author-name .td-author-line {
			display: none !important;
		}
	</style>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// Only run on single post/page views
			if (!document.body.classList.contains('single') &&
				!document.body.classList.contains('page')) {
				return;
			}

			// Find all meta-info containers
			var metaContainers = document.querySelectorAll('.meta-info');

			metaContainers.forEach(function (container) {
				// Get all direct children
				var children = Array.from(container.children);

				// Find the author link and date elements
				var authorElement = children.find(el => el.tagName === 'A');
				var dateElement = children.find(el => el.classList.contains('td-post-date'));

				// If both elements exist and no bullet separator between them
				if (authorElement && dateElement) {
					// Check if there's already text with a bullet between them
					var insertAfter = authorElement;
					var hasExistingBullet = false;

					// Check nodes between author and date
					var currentNode = authorElement.nextSibling;
					while (currentNode && currentNode !== dateElement) {
						if (currentNode.nodeType === Node.TEXT_NODE &&
							currentNode.textContent.includes('•')) {
							hasExistingBullet = true;
							break;
						}
						currentNode = currentNode.nextSibling;
					}

					// If no bullet exists, add one
					if (!hasExistingBullet) {
						var bullet = document.createTextNode(' • ');
						if (insertAfter.nextSibling) {
							container.insertBefore(bullet, insertAfter.nextSibling);
						} else {
							container.insertBefore(bullet, dateElement);
						}
					}
				}
			});

			// Also handle the standard theme format
			var authorElements = document.querySelectorAll('.td-post-header .td-post-author-name');

			authorElements.forEach(function (el) {
				// Check if there's a date element next to it
				if (el.nextElementSibling &&
					el.nextElementSibling.classList.contains('td-post-date')) {

					// If no bullet already exists
					if (!el.innerHTML.includes('•')) {
						// Remove any existing dash separator
						var dashElement = el.querySelector('.td-author-line');
						if (dashElement) {
							dashElement.remove();
						}

						// Add bullet separator
						var bullet = document.createElement('span');
						bullet.innerHTML = ' • ';
						el.insertAdjacentElement('afterend', bullet);
					}
				}
			});
		});
	</script>
	<?php
}
?>

