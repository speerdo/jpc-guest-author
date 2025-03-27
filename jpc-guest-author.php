<?php
/*
Plugin Name: JPC Guest Author Plugin
Plugin URI: http://www.jewishpolicycenter.org
Description: Custom functionality for the Guest Author Post Type
Version: 1.0
Author: JPC
Author URI: http://www.jewishpolicycenter.org
License: GPL2
*/

require_once('includes/jpc-guest-author-posts.php');
require_once('includes/jpc-author-display.php');

class JPC_Author_Widget extends WP_Widget
{

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct()
	{
		$widget_ops = array(
			'class_name' => 'jpc_author_widget',
			'description' => 'JPC Author Widget',
		);
		parent::__construct('jpc_author_widget', 'JPC Author Widget', $widget_ops);
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget($args, $instance)
	{
		// Add validation
		if (empty($instance['authortags'])) {
			return;
		}

		// Sanitize input
		$authortags = array_map('sanitize_text_field', explode(',', $instance['authortags']));
		$tids = array();
		$tnames = array();

		foreach ($authortags as $t) {
			$term = get_term_by('slug', trim($t), 'jpc_guest_author_tags');
			if ($term && !is_wp_error($term)) {
				$tids[] = $term->term_id;
				$tnames[] = $term->name;
			}
		}

		// Bail if no valid terms found
		if (empty($tids)) {
			return;
		}

		// Use proper pagination
		$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
		?>
		<div class="td_block_wrap td_block_9 pb-border-top">
			<h4 class="block-title">
				<?php if (($instance['title']) > "") {
					echo "<span> " . $instance['title'] . "</span>";
				} else { ?>
					<span>Articles by: <?php
					$c = 0;
					foreach ($tnames as $t) {
						if ($c > 0) {
							echo ", ";
						}
						echo $t;
						$c++;
					} ?></span>
				<?php } ?>
			</h4>
			<?php

			$args2 = array(
				'post_type' => 'post',
				'posts_per_page' => $instance['postcount'],
				'orderby' => 'date',
				'order' => 'DESC',
				'paged' => $paged,
				'tax_query' => array(
					array(
						'taxonomy' => 'jpc_guest_author_tags',
						'terms' => $tids,
					),
				)
			);
			$loop2 = new WP_Query($args2);
			?>

			<?php if ($loop2->have_posts()) { ?>
				<div class="jpc_ajax_content">
					<div class="ajax-response">
						<div class="innerajax">
							<?php

							while ($loop2->have_posts()):
								$loop2->the_post(); ?>


								<div class="td_block_inner">
									<div class="td-block-span12">
										<div class="td_module_8 td_module_wrap">
											<div class="item-details">
												<h3 class="entry-title td-module-title"><a href="<?php the_permalink(); ?>" rel="bookmark"
														title="<?php the_title(); ?>"><?php the_title(); ?></a></h3>
												<div class="meta-info">
													<?php
													$postid = get_the_ID();
													$thisPostACF = getPostACF($postid);
													if ($thisPostACF['jpc_book_by']) {
														echo "<strong>Book by:</strong> " . $thisPostACF['jpc_book_by'] . " <br /> ";
														echo "<strong>Reviewed by:</strong> " . jpc_author_display($postid);
													} else {
														echo jpc_author_display(get_the_ID());
													} ?>
													<?php if ($thisPostACF['jpc_custom_date_display']) {
														echo $thisPostACF['jpc_custom_date_display'];
													} else {
														the_date(false);
													}
													?>
												</div>
											</div>
										</div>
									</div>
								</div>

							<?php endwhile; ?>

						</div>
					</div>
					<div style="display:none">
						<div class="widget_post_count"><?php echo $instance['postcount']; ?></div>
						<div class="author_term_id"><?php echo $tids[0]; ?></div>
						<div class="author_term_name"><?php echo $tnames[0]; ?></div>
						<div class="action_name">theme_post_example3</div>
						<div class="offsetmax"><?php echo $loop2->found_posts; ?></div>
					</div>
					<div class="td-next-prev-wrap"><a href="#" class="ajax-post-back"><i
								class="td-icon-font td-icon-menu-left"></i></a><a href="#" class="ajax-post-next"><i
								class="td-icon-font td-icon-menu-right"></i></a></div>


				</div>
			<?php } ?>



		</div>
		<?php
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form($instance)
	{
		$title = "";
		$authortags = "";
		$postcount = 3;
		if (isset($instance['title'])) {
			$title = esc_attr($instance['title']);
		}

		if (isset($instance['authortags'])) {
			$authortags = esc_attr($instance['authortags']);
		}

		if (isset($instance['postcount'])) {
			$postcount = esc_attr($instance['postcount']);
		}

		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
				name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('authortags'); ?>"><?php _e('Author Tags'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('authortags'); ?>"
				name="<?php echo $this->get_field_name('authortags'); ?>" type="text" value="<?php echo $authortags; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('postcount'); ?>"><?php _e('Post Count'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('postcount'); ?>"
				name="<?php echo $this->get_field_name('postcount'); ?>" type="number" value="<?php echo $postcount; ?>" />
		</p>
		<?php
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['authortags'] = strip_tags($new_instance['authortags']);
		$instance['postcount'] = strip_tags($new_instance['postcount']);
		return $instance;
	}
}


add_action('widgets_init', function () {
	register_widget('JPC_Author_Widget');
});





add_action('wp_ajax_theme_post_example3', 'theme_post_example_init3');
add_action('wp_ajax_nopriv_theme_post_example3', 'theme_post_example_init3');
function theme_post_example_init3()
{
	// Add nonce verification
	check_ajax_referer('jpc_author_ajax', 'nonce');

	// Validate and sanitize inputs
	$post_count = isset($_POST['widget_post_count']) ? absint($_POST['widget_post_count']) : 3;
	$offset = isset($_POST['author_offset']) ? absint($_POST['author_offset']) : 0;
	$term_id = isset($_POST['author_term_id']) ? absint($_POST['author_term_id']) : 0;

	if (!$term_id) {
		wp_send_json_error('Invalid term ID');
		return;
	}

	$args3 = array(
		'post_type' => 'post',
		'posts_per_page' => $post_count,
		'offset' => $offset,
		'orderby' => 'date',
		'order' => 'DESC',
		'tax_query' => array(
			array(
				'taxonomy' => 'jpc_guest_author_tags',
				'terms' => $term_id,
				'field' => 'term_id',
			),
		)
	);

	$loop3 = new WP_Query($args3);

	if (!$loop3->have_posts()) {
		wp_send_json_error('No posts found');
		return;
	}

	ob_start();
	while ($loop3->have_posts()):
		$loop3->the_post(); ?>
		<div class="td_block_inner">
			<div class="td-block-span12">
				<div class="td_module_8 td_module_wrap">
					<div class="item-details">
						<h3 class="entry-title td-module-title"><a href="<?php the_permalink(); ?>" rel="bookmark"
								title="<?php the_title(); ?>"><?php the_title(); ?></a></h3>
						<div class="meta-info">
							<?php
							$postid = get_the_ID();
							$thisPostACF = getPostACF($postid);
							if ($thisPostACF['jpc_book_by']) {
								echo "<strong>Book by:</strong> " . $thisPostACF['jpc_book_by'] . " <br /> ";
								echo "<strong>Reviewed by:</strong> " . jpc_author_display($postid);
							} else {
								echo jpc_author_display(get_the_ID());
							} ?>
							<?php if ($thisPostACF['jpc_custom_date_display']) {
								echo $thisPostACF['jpc_custom_date_display'];
							} else {
								the_date(false);
							}
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php endwhile; ?>
	<?php
	$content = ob_get_clean();
	wp_send_json_success($content);
}
