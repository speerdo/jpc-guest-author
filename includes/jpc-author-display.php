<?php
/**
 * JPC Author Display Enhancement
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Get formatted guest author names for a post
 */
function jpc_get_guest_author_names($post_id)
{
	$authors = wp_get_post_terms($post_id, 'jpc_guest_author_tags');

	if (empty($authors) || is_wp_error($authors)) {
		return '';
	}

	$names = array();
	$links = array();

	foreach ($authors as $author) {
		$term_link = get_term_link($author);
		if (!is_wp_error($term_link)) {
			$links[] = '<a href="' . esc_url($term_link) . '">' . esc_html($author->name) . '</a>';
		} else {
			$names[] = esc_html($author->name);
		}
	}

	// Use links if available, otherwise just names
	$result = !empty($links) ? implode(', ', $links) : implode(', ', $names);

	// Add bullet if content exists
	if (!empty($result)) {
		$result .= ' <span class="bullet">&bull;</span> ';
	}

	return $result;
}

/**
 * Override author display
 */
function jpc_override_author($name)
{
	global $post;

	if (!$post || !isset($post->ID)) {
		return $name;
	}

	$guest_authors = jpc_get_guest_author_names($post->ID);
	return !empty($guest_authors) ? $guest_authors : $name;
}

/**
 * Fix author display in AJAX requests
 * - Supports both 'pid' and 'post_id' parameters for compatibility
 */
function jpc_ajax_get_author()
{
	// Support both parameter names for compatibility
	$post_id = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
	if ($post_id === 0) {
		$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
	}

	if ($post_id > 0) {
		$guest_authors = jpc_get_guest_author_names($post_id);
		if (!empty($guest_authors)) {
			echo $guest_authors;
			wp_die();
		}
	}

	// Fallback to default author
	echo get_the_author_meta('display_name', get_post_field('post_author', $post_id));
	wp_die();
}

/**
 * Add JavaScript to fix author display in all contexts
 * - Enhanced to work with TD theme modules
 * - Improved selectors for both single posts and previews
 * - Added observer for dynamically loaded content
 */
function jpc_add_author_script()
{
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// Function to find post ID from various contexts
			function getPostId(element) {
				// Check for article container
				var article = element.closest('article, .td_module_wrap, .item-details');
				if (!article) return null;

				// Try to get ID from article ID attribute
				if (article.id && article.id.match(/^post-(\d+)$/)) {
					return article.id.replace('post-', '');
				}

				// Try data attribute
				if (article.dataset.postId) {
					return article.dataset.postId;
				}

				// Try to find from permalink
				var link = article.querySelector('.entry-title a, h3 a, a.td-image-wrap');
				if (!link || !link.href) return null;

				// Try both URL formats
				var match = link.href.match(/\?p=(\d+)/) || link.href.match(/\/(\d+)\//);
				return match ? match[1] : null;
			}

			// Function to process author elements
			function processAuthorElements() {
				// Comprehensive selector for all possible author elements
				var authorElements = document.querySelectorAll(
					'.td-post-author-name a, ' +
					'.meta-info a[rel="author"], ' +
					'.meta-info a:first-child, ' +
					'.td-post-author-name, ' +
					'.td-module-meta-info a[rel="author"]'
				);

				authorElements.forEach(function (element) {
					// Skip already processed elements
					if (element.dataset.jpcProcessed) return;
					element.dataset.jpcProcessed = true;

					var postId = getPostId(element);
					if (!postId) return;

					var xhr = new XMLHttpRequest();
					// Support both parameter names for compatibility
					xhr.open('GET', '<?php echo admin_url('admin-ajax.php'); ?>?action=jpc_ajax_author&post_id=' + postId);

					xhr.onload = function () {
						if (xhr.status === 200 && xhr.responseText) {
							var response = xhr.responseText.trim();
							if (!response) return;

							// Update the element appropriately
							if (element.tagName.toLowerCase() === 'a') {
								// If it's a link, replace with the author HTML
								if (element.parentNode &&
									(element.parentNode.classList.contains('td-post-author-name') ||
										element.parentNode.classList.contains('meta-info'))) {
									element.parentNode.innerHTML = response;
								} else {
									element.outerHTML = response;
								}
							} else {
								// If it's a container, replace inner HTML
								element.innerHTML = response;
							}
						}
					};

					xhr.send();
				});
			}

			// Initial processing
			setTimeout(processAuthorElements, 500);

			// Process after AJAX content loads
			document.addEventListener('td_ajax_block_request_done', function () {
				setTimeout(processAuthorElements, 300);
			});

			// Watch for dynamically added content
			var observer = new MutationObserver(function (mutations) {
				var needsProcessing = false;

				mutations.forEach(function (mutation) {
					if (mutation.addedNodes && mutation.addedNodes.length) {
						for (var i = 0; i < mutation.addedNodes.length; i++) {
							var node = mutation.addedNodes[i];
							if (node.nodeType === 1 &&
								(node.matches('.td_module_wrap, article, .td-block-span12') ||
									node.querySelector('.td-post-author-name, .meta-info'))) {
								needsProcessing = true;
								break;
							}
						}
					}
				});

				if (needsProcessing) {
					setTimeout(processAuthorElements, 100);
				}
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		});
	</script>
	<?php
}

/**
 * Filter for TD module author HTML
 */
function jpc_filter_module_author($author_html, $post_obj = null)
{
	// Get post ID
	$post_id = null;
	if (is_object($post_obj) && isset($post_obj->ID)) {
		$post_id = $post_obj->ID;
	} elseif (is_numeric($post_obj)) {
		$post_id = $post_obj;
	} elseif (in_the_loop()) {
		$post_id = get_the_ID();
	}

	// If we have a post ID, try to get guest author
	if ($post_id) {
		$guest_author = jpc_get_guest_author_names($post_id);
		if (!empty($guest_author)) {
			return $guest_author;
		}
	}

	return $author_html;
}

// Initialize filters for TD theme modules if available
function jpc_init_td_filters()
{
	if (function_exists('td_util')) {
		add_filter('td_module_single_author', 'jpc_filter_module_author', 20, 2);
		add_filter('td_mod_single_author', 'jpc_filter_module_author', 20, 2);
		add_filter('td_mod_loop_author', 'jpc_filter_module_author', 20, 2);
	}
}
add_action('init', 'jpc_init_td_filters');

// Remove problematic filter
remove_filter('the_author_link', 'expert_to_upper');

// Add our filters with high priority
add_filter('the_author', 'jpc_override_author', 20);
add_filter('get_the_author_display_name', 'jpc_override_author', 20);

// Add AJAX handlers - support both action names for compatibility
add_action('wp_ajax_jpc_ajax_author', 'jpc_ajax_get_author');
add_action('wp_ajax_nopriv_jpc_ajax_author', 'jpc_ajax_get_author');

// Add additional AJAX handlers for our new code
add_action('wp_ajax_jpc_get_author', 'jpc_ajax_get_author');
add_action('wp_ajax_nopriv_jpc_get_author', 'jpc_ajax_get_author');

// Add JavaScript
add_action('wp_footer', 'jpc_add_author_script');

/**
 * Fix bullet separator for event pages
 */
function jpc_fix_event_bullet_separator()
{
	// Only run this on single posts (which includes events)
	if (!is_single()) {
		return;
	}
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			// Target the specific meta-info structure in events
			var metaInfos = document.querySelectorAll('.meta-info');

			metaInfos.forEach(function (metaInfo) {
				// Look for author link
				var authorLink = metaInfo.querySelector('a[href*="/authors/"]');
				console.log('JPC: Found author link', authorLink);
				if (authorLink) {
					// Check if there's already a bullet after the author link
					var nextNode = authorLink.nextSibling;
					var hasBullet = false;

					while (nextNode && !hasBullet) {
						// If it's a text node or element containing a bullet
						if ((nextNode.nodeType === 3 && nextNode.textContent.includes('•')) ||
							(nextNode.nodeType === 1 && nextNode.innerHTML.includes('•'))) {
							hasBullet = true;
							break;
						}
						nextNode = nextNode.nextSibling;
					}

					// If no bullet is found, insert one immediately after the author link
					if (!hasBullet) {
						// Create bullet element with non-breaking spaces for better spacing
						var bulletSpan = document.createElement('span');
						bulletSpan.className = 'bullet';
						bulletSpan.innerHTML = '&nbsp;&bull;&nbsp;';

						// Insert immediately after author link
						authorLink.insertAdjacentElement('afterend', bulletSpan);

						// Log for debugging
						console.log('JPC: Inserted bullet after author in event template');
					}
				}
			});
		});
	</script>
	<?php
}
add_action('wp_footer', 'jpc_fix_event_bullet_separator', 99); // High priority to run after other scripts

/**
 * Remove time from event date display in meta-info
 */
function jpc_fix_event_date_display()
{
	// Only run on single posts
	if (!is_single()) {
		return;
	}
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			console.log('JPC: Date formatter script running');

			// Find all date text in meta-info areas
			var metaInfos = document.querySelectorAll('.meta-info');

			metaInfos.forEach(function (metaInfo) {
				// Process all text nodes directly in the meta-info
				var childNodes = metaInfo.childNodes;

				for (var i = 0; i < childNodes.length; i++) {
					var node = childNodes[i];

					// Check if it's a text node
					if (node.nodeType === 3) {
						var text = node.textContent;

						// If text contains "at" followed by time, replace with just the date part
						if (text.indexOf(' at ') !== -1) {
							var dateParts = text.split(' at ');
							// Replace the text node with just the date part
							node.textContent = dateParts[0];
							console.log('JPC: Fixed date format by removing "at" time');
						}
					}
				}
			});
		});
	</script>
	<?php
}
add_action('wp_footer', 'jpc_fix_event_date_display', 100); // Very high priority

/**
 * Add CSS to hide time part in event date display
 */
function jpc_add_event_date_css()
{
	// Only run on single posts
	if (!is_single()) {
		return;
	}
	?>
	<style>
		/* Hide time part in event dates */
		.meta-info .jpc-event-time {
			display: none;
		}
	</style>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			console.log('JPC: Running event time wrapper script');

			// Find all meta-info areas
			var metaInfos = document.querySelectorAll('.meta-info');

			metaInfos.forEach(function (metaInfo) {
				// Check content for "at" time format
				var html = metaInfo.innerHTML;
				if (html.indexOf(' at ') !== -1) {
					// Replace text format with wrapped time
					// Use non-greedy match to get everything after "at " but before the next HTML tag
					var newHtml = html.replace(/(\s+at\s+)([^<]+)/g, '<span class="jpc-event-date">$1<span class="jpc-event-time">$2</span></span>');
					metaInfo.innerHTML = newHtml;
					console.log('JPC: Wrapped time part in meta-info with CSS class');
				}
			});
		});
	</script>
	<?php
}
add_action('wp_head', 'jpc_add_event_date_css', 100);

/**
 * Simple jQuery solution to fix date format - More aggressive version
 * This is a strong approach that directly manipulates the DOM and text nodes
 */
function jpc_jquery_fix_date_format()
{
	?>
	<script>
		jQuery(document).ready(function ($) {
			console.log('JPC: Enhanced date formatter running');

			// Function to process all text nodes in an element
			function processTextNodes(parentNode) {
				if (!parentNode) return;

				// Process all child nodes
				$(parentNode).contents().each(function () {
					// If it's a text node containing time format
					if (this.nodeType === 3) {
						var text = this.textContent || this.nodeValue || '';
						if (text.indexOf(' at ') !== -1) {
							console.log('JPC: Found date with "at" in text node', text);
							var datePart = text.split(' at ')[0];
							this.textContent = datePart;
							console.log('JPC: Changed to', datePart);
						}
					} else if (this.nodeType === 1) {
						// For element nodes, process their text nodes too
						processTextNodes(this);
					}
				});
			}

			// Direct approach 1: Process .meta-info
			$('.meta-info').each(function () {
				console.log('JPC: Processing meta-info div');
				processTextNodes(this);
			});

			// Direct approach 2: Try another method with regular expressions
			// Gets all text in the document and replaces date formats
			var allHTML = $('body').html();
			if (allHTML.indexOf(' at ') !== -1) {
				console.log('JPC: Found "at" in page HTML');

				// Process specific areas more aggressively
				$('.meta-info, .td-post-header, .td-post-title').each(function () {
					var html = $(this).html();
					if (html && html.indexOf(' at ') !== -1) {
						// Use regex to target the date format but only in non-HTML content
						var newHtml = html.replace(/([A-Za-z]+day, [A-Za-z]+ \d+, \d{4}) at \d+[APM]{2}/g, '$1');
						$(this).html(newHtml);
						console.log('JPC: Replaced date format using regex');
					}
				});
			}

			// Direct approach 3: Brute force - add a visible mask over the time part
			$('.meta-info:contains("at")').each(function () {
				console.log('JPC: Adding mask div to meta-info containing "at"');
				$(this).css({
					'position': 'relative',
					'overflow': 'hidden'
				}).append('<div style="position:absolute;right:0;top:0;bottom:0;width:50%;background:white;z-index:10;"></div>');
			});
		});
	</script>
	<?php
}
add_action('wp_footer', 'jpc_jquery_fix_date_format', 999);

/**
 * Vanilla JavaScript fallback to fix date format
 * No jQuery dependency - direct DOM manipulation
 */
function jpc_vanilla_js_fix_date()
{
	?>
	<script>
		// Wait for DOM to be fully loaded
		document.addEventListener('DOMContentLoaded', function () {
			console.log('JPC: Vanilla JS date formatter running');

			// Function to process all text nodes in an element
			function processTextNodes(element) {
				if (!element) return;

				// Process direct text nodes
				var childNodes = element.childNodes;
				for (var i = 0; i < childNodes.length; i++) {
					var node = childNodes[i];

					// Process text nodes
					if (node.nodeType === 3) { // Text node
						var text = node.textContent || node.nodeValue || '';
						if (text.indexOf(' at ') !== -1) {
							console.log('JPC Vanilla: Found date with "at" in text node', text);
							var datePart = text.split(' at ')[0];
							node.textContent = datePart;
							console.log('JPC Vanilla: Changed to', datePart);
						}
					} else if (node.nodeType === 1) { // Element node
						// Recursively process element's children
						processTextNodes(node);
					}
				}
			}

			// Get all meta-info elements
			var metaInfos = document.querySelectorAll('.meta-info');

			// Process each meta-info
			for (var i = 0; i < metaInfos.length; i++) {
				var metaInfo = metaInfos[i];
				console.log('JPC Vanilla: Processing meta-info div');
				processTextNodes(metaInfo);

				// Brute force approach - add CSS to hide part of the meta-info
				if (metaInfo.innerHTML.indexOf(' at ') !== -1) {
					console.log('JPC Vanilla: Adding style to hide time part');
					metaInfo.style.position = 'relative';
					metaInfo.style.overflow = 'hidden';

					// Create mask div
					var mask = document.createElement('div');
					mask.style.position = 'absolute';
					mask.style.right = '0';
					mask.style.top = '0';
					mask.style.bottom = '0';
					mask.style.width = '50%';
					mask.style.background = 'white';
					mask.style.zIndex = '10';

					// Add mask to meta-info
					metaInfo.appendChild(mask);
				}
			}

			// Add direct CSS in case all else fails
			var style = document.createElement('style');
			style.textContent = '.meta-info { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 50%; }';
			document.head.appendChild(style);
		});
	</script>
	<?php
}
add_action('wp_footer', 'jpc_vanilla_js_fix_date', 1000); // Even higher priority
