<?php
/**
 * The few SEO plugin APIs Pergel\Seo\Trail reads, for static analysis.
 *
 * @package Pergel
 */

// phpcs:ignoreFile -- stub declarations only, never loaded at runtime.

namespace RankMath\Frontend {
	class Breadcrumbs {
		/** @return Breadcrumbs|false */
		public static function get() {}

		/** @return array<int, array<int|string, mixed>> */
		public function get_crumbs() {}
	}
}

namespace The_SEO_Framework\Meta {
	class Breadcrumbs {
		/** @return array<int, array<string, mixed>> */
		public static function get_breadcrumb_list() {}
	}
}

namespace Yoast\WP\SEO {
	class Main {
		/** @var Surfaces\Meta_Surface */
		public $meta;
	}
}

namespace Yoast\WP\SEO\Surfaces {
	class Meta_Surface {
		/** @return \Yoast\WP\SEO\Surfaces\Values\Meta|false */
		public function for_current_page() {}
	}
}

namespace Yoast\WP\SEO\Surfaces\Values {
	/** @property array<int, mixed> $breadcrumbs */
	class Meta {}
}

namespace AIOSEO\Plugin {
	class AIOSEO {
		/** @var object{frontend: \AIOSEO\Plugin\Common\Breadcrumbs\Frontend} */
		public $breadcrumbs;
	}
}

namespace AIOSEO\Plugin\Common\Breadcrumbs {
	class Frontend {
		/** @return array<int, array<string, mixed>> */
		public function getBreadcrumbs() {}
	}
}

namespace {
	/** @return \Yoast\WP\SEO\Main */
	function YoastSEO() {}

	/** @return \AIOSEO\Plugin\AIOSEO */
	function aioseo() {}
}
