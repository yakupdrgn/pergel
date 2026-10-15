<?php
/**
 * Title: Comments
 * Slug: pergel/hidden-comments
 * Inserter: no
 * Description: Comment list, pagination and form. Renders nothing when comments are closed and there are none.
 *
 * @package Pergel
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:comments {"style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}}} -->
<div class="wp-block-comments" style="margin-top:var(--wp--preset--spacing--60)"><!-- wp:comments-title {"level":2,"fontSize":"x-large"} /-->

<!-- wp:comment-template -->
<!-- wp:group {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--50)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:avatar {"size":40,"style":{"border":{"radius":"50%"}}} /-->

<!-- wp:comment-author-name {"fontSize":"medium"} /-->

<!-- wp:comment-date {"fontSize":"small","textColor":"muted"} /--></div>
<!-- /wp:group -->

<!-- wp:comment-content /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex"},"fontSize":"small"} -->
<div class="wp-block-group has-small-font-size"><!-- wp:comment-reply-link /-->

<!-- wp:comment-edit-link /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:comment-template -->

<!-- wp:comments-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:comments-pagination-previous /-->

<!-- wp:comments-pagination-numbers /-->

<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination -->

<!-- wp:post-comments-form /--></div>
<!-- /wp:comments -->
