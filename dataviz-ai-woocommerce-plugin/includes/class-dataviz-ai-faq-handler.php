<?php
/**
 * FAQ Handler for Dataviz AI WooCommerce Plugin
 *
 * Handles common questions about dataviz queries and provides quick answers.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FAQ Handler Class
 */
class Dataviz_AI_FAQ_Handler {

	/**
	 * FAQ entries with questions and answers.
	 *
	 * @var array
	 */
	protected $faq_entries = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_faq_entries();
	}

	/**
	 * Initialize FAQ entries.
	 *
	 * @return void
	 */
	protected function init_faq_entries() {
		$this->faq_entries = array(
			// General queries
			array(
				'keywords'   => array( 'what can you do', 'what data can', 'what information', 'what questions', 'help', 'capabilities' ),
				'question'   => __( 'What can you help me with?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'I can help you analyze your WooCommerce store data including orders, products, customers, sales, revenue, inventory, stock levels, categories, tags, coupons, and refunds. You can ask me questions like "Show me recent orders", "What are my top products?", "How many customers do I have?", or request charts and visualizations of your data.', 'dataviz-ai-woocommerce' ),
			),
			array(
				'keywords'   => array( 'how to', 'how do i', 'how can i', 'tutorial', 'guide' ),
				'question'   => __( 'How do I use this tool?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Simply type your question in natural language! For example, you can ask "Show me my recent orders", "What are my top 10 products?", "Display revenue for this month", or "Show me low stock products". I\'ll fetch the relevant data from your WooCommerce store and provide answers with visualizations when appropriate.', 'dataviz-ai-woocommerce' ),
			),

			// Orders queries
			array(
				'keywords'   => array( 'orders', 'order list', 'recent orders', 'show orders' ),
				'question'   => __( 'How can I see my orders?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'You can ask questions like "Show me recent orders", "List orders from last week", "Display orders by status", or "Show orders between dates". I can provide order lists, statistics, and time-series data grouped by hour, day, week, or month.', 'dataviz-ai-woocommerce' ),
			),
			array(
				'keywords'   => array( 'total revenue', 'total sales', 'revenue', 'total income', 'how much revenue' ),
				'question'   => __( 'How do I check my total revenue?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Ask me "What is my total revenue?", "Show total sales", "Revenue this month", or "Total revenue for last week". I can calculate totals, averages, and revenue breakdowns by status, date range, or time period.', 'dataviz-ai-woocommerce' ),
			),
			array(
				'keywords'   => array( 'order status', 'order statistics', 'status breakdown', 'orders by status' ),
				'question'   => __( 'Can I see order statistics?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Yes! Ask "Show order statistics", "Orders by status", "How many orders are processing?", or "Order breakdown by status". I can provide counts, totals, averages, and breakdowns by order status (completed, processing, pending, cancelled, etc.).', 'dataviz-ai-woocommerce' ),
			),

			// Products queries
			array(
				'keywords'   => array( 'top products', 'best products', 'best selling', 'popular products', 'top selling' ),
				'question'   => __( 'How can I see my top products?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Ask "Show me top products", "What are my best selling products?", "Display top 10 products", or "Most popular products". I\'ll show you products ranked by total sales with their sales counts and prices.', 'dataviz-ai-woocommerce' ),
			),
			array(
				'keywords'   => array( 'products by category', 'category products', 'products in category' ),
				'question'   => __( 'Can I filter products by category?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Yes! Ask "Show products in [category name]", "Products by category", or "List all categories". I can filter products by category and show you all available product categories in your store.', 'dataviz-ai-woocommerce' ),
			),

			// Inventory/Stock queries
			array(
				'keywords'   => array( 'low stock', 'out of stock', 'stock levels', 'inventory' ),
				'question'   => __( 'How do I check stock levels?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Ask "Show me low stock products", "What products are low on stock?", "Display inventory", or "Stock levels". I can show you products with low stock (below threshold), all inventory levels, or products that are out of stock.', 'dataviz-ai-woocommerce' ),
			),
			array(
				'keywords'   => array( 'inventory', 'current inventory', 'stock status' ),
				'question'   => __( 'Can I see all inventory?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Yes! Ask "Show me inventory", "Display all inventory", "Current stock levels", or "Inventory overview". I\'ll show you all products with their current stock quantities.', 'dataviz-ai-woocommerce' ),
			),

			// Customers queries
			array(
				'keywords'   => array( 'customers', 'customer list', 'total customers', 'how many customers' ),
				'question'   => __( 'How can I see my customers?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Ask "Show me customers", "List all customers", "How many customers do I have?", or "Customer summary". I can provide customer lists, total counts, and customer statistics including average lifetime value.', 'dataviz-ai-woocommerce' ),
			),

			// Charts and visualizations
			array(
				'keywords'   => array( 'chart', 'graph', 'visualization', 'pie chart', 'bar chart', 'line chart' ),
				'question'   => __( 'Can you create charts?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Yes! When you ask for data that can be visualized, charts are automatically generated. Try asking "Show me a chart of sales by status", "Display revenue chart", "Create a pie chart of orders", or "Bar chart of top products". The system will automatically render appropriate visualizations based on your data.', 'dataviz-ai-woocommerce' ),
			),

			// Time-based queries
			array(
				'keywords'   => array( 'this month', 'last month', 'this week', 'last week', 'today', 'yesterday', 'date range' ),
				'question'   => __( 'Can I filter by date?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Yes! You can ask "Orders this month", "Revenue last week", "Sales from [date] to [date]", "Orders between dates", or "Revenue by day/week/month". I support date ranges, specific periods, and time-series grouping.', 'dataviz-ai-woocommerce' ),
			),

			// Coupons and discounts
			array(
				'keywords'   => array( 'coupons', 'discounts', 'promo codes', 'coupon codes' ),
				'question'   => __( 'Can I see coupon information?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Yes! Ask "Show me coupons", "List all coupons", or "Display active coupons". I can show you all coupons with their codes, discount amounts, usage limits, and expiration dates.', 'dataviz-ai-woocommerce' ),
			),

			// Refunds
			array(
				'keywords'   => array( 'refunds', 'refunded orders', 'returns' ),
				'question'   => __( 'Can I see refund information?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Yes! Ask "Show me refunds", "List refunded orders", or "Refund statistics". I can show you refund details including amounts, dates, and related order information.', 'dataviz-ai-woocommerce' ),
			),

			// Categories and tags
			array(
				'keywords'   => array( 'categories', 'product categories', 'all categories' ),
				'question'   => __( 'Can I see product categories?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Yes! Ask "Show me categories", "List all categories", or "Product categories". I\'ll display all product categories in your store with their names and IDs.', 'dataviz-ai-woocommerce' ),
			),
			array(
				'keywords'   => array( 'tags', 'product tags', 'all tags' ),
				'question'   => __( 'Can I see product tags?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Yes! Ask "Show me tags", "List all tags", or "Product tags". I\'ll display all product tags in your store.', 'dataviz-ai-woocommerce' ),
			),

			// Error/support questions
			array(
				'keywords'   => array( 'not working', 'error', 'problem', 'issue', 'bug', 'broken' ),
				'question'   => __( 'What if something is not working?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'If you encounter an error or a feature you need isn\'t available, I\'ll let you know and offer to submit a feature request. Just confirm and I\'ll notify the administrators. You can also check the plugin settings to ensure your API keys are configured correctly.', 'dataviz-ai-woocommerce' ),
			),
			array(
				'keywords'   => array( 'unsupported', 'not available', 'not supported', 'missing feature', 'can\'t find' ),
				'question'   => __( 'What if a feature is not supported?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'If you ask about a data type or feature that isn\'t currently supported, I\'ll inform you and offer to submit a feature request. Just say "yes" or "request this feature" and I\'ll submit it to the administrators for consideration.', 'dataviz-ai-woocommerce' ),
			),

			// Limits and usage
			array(
				'keywords'   => array( 'limit', 'quota', 'usage', 'how many questions', 'monthly limit' ),
				'question'   => __( 'Are there limits on how many questions I can ask?', 'dataviz-ai-woocommerce' ),
				'answer'     => __( 'Free plans have monthly question limits. If you reach your limit, you\'ll see a message with an option to upgrade to Pro for unlimited questions. Check your usage in the plugin settings or ask "What is my usage?" to see your current status.', 'dataviz-ai-woocommerce' ),
			),
		);
	}

	/**
	 * Check if a user question matches any FAQ entry.
	 *
	 * @param string $question User's question.
	 * @return array|false FAQ entry with answer, or false if no match.
	 */
	public function find_faq_match( $question ) {
		if ( empty( $question ) ) {
			return false;
		}

		$question_lower = strtolower( trim( $question ) );

		// Score each FAQ entry based on keyword matches
		$matches = array();
		foreach ( $this->faq_entries as $index => $entry ) {
			$score = 0;
			$matched_keywords = array();

			foreach ( $entry['keywords'] as $keyword ) {
				if ( strpos( $question_lower, strtolower( $keyword ) ) !== false ) {
					$score++;
					$matched_keywords[] = $keyword;
				}
			}

			if ( $score > 0 ) {
				$matches[] = array(
					'index'            => $index,
					'score'            => $score,
					'matched_keywords' => $matched_keywords,
					'entry'            => $entry,
				);
			}
		}

		// Return the best match (highest score)
		if ( ! empty( $matches ) ) {
			// Sort by score descending
			usort( $matches, function( $a, $b ) {
				return $b['score'] - $a['score'];
			} );

			$best_match = $matches[0];

			// Require at least 1 keyword match (already ensured) and at least 30% of keywords matched or score >= 2
			$required_score = max( 1, ceil( count( $best_match['entry']['keywords'] ) * 0.3 ) );
			if ( $best_match['score'] >= $required_score || $best_match['score'] >= 2 ) {
				return $best_match['entry'];
			}
		}

		return false;
	}

	/**
	 * Get all FAQ entries (for admin display or API).
	 *
	 * @return array All FAQ entries.
	 */
	public function get_all_faqs() {
		return $this->faq_entries;
	}

	/**
	 * Get FAQ entries by category or keyword.
	 *
	 * @param string $category Category name (optional).
	 * @return array Filtered FAQ entries.
	 */
	public function get_faqs_by_category( $category = '' ) {
		if ( empty( $category ) ) {
			return $this->faq_entries;
		}

		// Simple category filtering based on keywords
		$category_keywords = array(
			'orders'      => array( 'orders', 'revenue', 'sales', 'order status' ),
			'products'    => array( 'products', 'top products', 'category', 'categories' ),
			'inventory'   => array( 'stock', 'inventory', 'low stock' ),
			'customers'   => array( 'customers', 'customer' ),
			'visualization' => array( 'chart', 'graph', 'visualization' ),
			'general'     => array( 'what can', 'how to', 'help', 'capabilities' ),
		);

		if ( ! isset( $category_keywords[ $category ] ) ) {
			return array();
		}

		$filtered = array();
		foreach ( $this->faq_entries as $entry ) {
			foreach ( $entry['keywords'] as $keyword ) {
				foreach ( $category_keywords[ $category ] as $cat_keyword ) {
					if ( stripos( $keyword, $cat_keyword ) !== false ) {
						$filtered[] = $entry;
						break 2; // Break both loops
					}
				}
			}
		}

		return $filtered;
	}

	/**
	 * Format FAQ answer for display.
	 *
	 * @param array  $faq_entry FAQ entry.
	 * @param string $format    Format: 'text', 'html', 'json'.
	 * @return string|array Formatted answer.
	 */
	public function format_faq_answer( $faq_entry, $format = 'text' ) {
		switch ( $format ) {
			case 'html':
				return sprintf(
					'<div class="dataviz-ai-faq-answer"><h4>%s</h4><p>%s</p></div>',
					esc_html( $faq_entry['question'] ),
					esc_html( $faq_entry['answer'] )
				);

			case 'json':
				return array(
					'type'        => 'faq',
					'question'    => $faq_entry['question'],
					'answer'      => $faq_entry['answer'],
					'keywords'    => $faq_entry['keywords'],
				);

			case 'text':
			default:
				return sprintf( '%s\n\n%s', $faq_entry['question'], $faq_entry['answer'] );
		}
	}
}
