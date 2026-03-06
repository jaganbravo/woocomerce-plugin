<?php
/**
 * LangChain-style prompt template system for structured prompting.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prompt template class for building structured, reusable prompts.
 */
class Dataviz_AI_Prompt_Template {

	/**
	 * Template string with placeholders.
	 *
	 * @var string
	 */
	private $template;

	/**
	 * Default variables for the template.
	 *
	 * @var array
	 */
	private $default_variables;

	/**
	 * Examples to include in the prompt.
	 *
	 * @var array
	 */
	private $examples;

	/**
	 * Instructions/rules to include.
	 *
	 * @var array
	 */
	private $instructions;

	/**
	 * Constructor.
	 *
	 * @param string $template Template string with {variable} placeholders.
	 * @param array  $defaults  Default variable values.
	 */
	public function __construct( $template = '', array $defaults = array() ) {
		$this->template         = $template;
		$this->default_variables = $defaults;
		$this->examples         = array();
		$this->instructions     = array();
	}

	/**
	 * Set the template string.
	 *
	 * @param string $template Template with {variable} placeholders.
	 * @return self
	 */
	public function set_template( $template ) {
		$this->template = $template;
		return $this;
	}

	/**
	 * Add an example (few-shot learning).
	 *
	 * @param string $input  Example input/question.
	 * @param string $output Example output/answer.
	 * @return self
	 */
	public function add_example( $input, $output ) {
		$this->examples[] = array(
			'input'  => $input,
			'output' => $output,
		);
		return $this;
	}

	/**
	 * Add multiple examples.
	 *
	 * @param array $examples Array of ['input' => ..., 'output' => ...] pairs.
	 * @return self
	 */
	public function add_examples( array $examples ) {
		foreach ( $examples as $example ) {
			if ( isset( $example['input'] ) && isset( $example['output'] ) ) {
				$this->add_example( $example['input'], $example['output'] );
			}
		}
		return $this;
	}

	/**
	 * Add an instruction/rule.
	 *
	 * @param string $instruction Instruction text.
	 * @return self
	 */
	public function add_instruction( $instruction ) {
		$this->instructions[] = $instruction;
		return $this;
	}

	/**
	 * Add multiple instructions.
	 *
	 * @param array $instructions Array of instruction strings.
	 * @return self
	 */
	public function add_instructions( array $instructions ) {
		foreach ( $instructions as $instruction ) {
			$this->add_instruction( $instruction );
		}
		return $this;
	}

	/**
	 * Format the template with variables.
	 *
	 * @param array $variables Variables to replace in template.
	 * @return string Formatted prompt.
	 */
	public function format( array $variables = array() ) {
		// Merge default variables with provided variables (provided take precedence).
		$vars = array_merge( $this->default_variables, $variables );

		// Replace placeholders in template.
		$prompt = $this->template;
		foreach ( $vars as $key => $value ) {
			$placeholder = '{' . $key . '}';
			if ( is_array( $value ) ) {
				// Handle array values (e.g., for lists).
				$value = $this->format_array_value( $value );
			}
			$prompt = str_replace( $placeholder, $value, $prompt );
		}

		// Add instructions section if any.
		if ( ! empty( $this->instructions ) ) {
			$instructions_section = "\n\n## Instructions\n";
			foreach ( $this->instructions as $index => $instruction ) {
				$instructions_section .= ( $index + 1 ) . '. ' . $instruction . "\n";
			}
			$prompt .= $instructions_section;
		}

		// Add examples section if any.
		if ( ! empty( $this->examples ) ) {
			$examples_section = "\n\n## Examples\n";
			foreach ( $this->examples as $index => $example ) {
				$examples_section .= "\n### Example " . ( $index + 1 ) . "\n";
				$examples_section .= "**Input:** " . $example['input'] . "\n";
				$examples_section .= "**Output:** " . $example['output'] . "\n";
			}
			$prompt .= $examples_section;
		}

		return $prompt;
	}

	/**
	 * Format array value for display.
	 *
	 * @param array $array Array to format.
	 * @return string Formatted string.
	 */
	private function format_array_value( array $array ) {
		if ( empty( $array ) ) {
			return 'None';
		}

		// Check if it's an associative array (key-value pairs).
		if ( array_keys( $array ) !== range( 0, count( $array ) - 1 ) ) {
			$formatted = array();
			foreach ( $array as $key => $value ) {
				if ( is_array( $value ) ) {
					$formatted[] = $key . ': ' . wp_json_encode( $value );
				} else {
					$formatted[] = $key . ': ' . $value;
				}
			}
			return implode( "\n", $formatted );
		}

		// Simple indexed array.
		return implode( ', ', $array );
	}

	/**
	 * Build a message array for OpenAI API.
	 *
	 * @param string $role      Message role (system, user, assistant).
	 * @param array  $variables Variables to format template with.
	 * @return array Message array.
	 */
	public function build_message( $role = 'user', array $variables = array() ) {
		return array(
			'role'    => $role,
			'content' => $this->format( $variables ),
		);
	}

	/**
	 * Get predefined template for data analysis.
	 *
	 * @return self
	 */
	public static function data_analysis() {
		$template = new self(
			'You are a WooCommerce data analyst. The user asked: "{question}". I have just fetched the relevant data from the WooCommerce store using tools.',
			array(
				'question' => '',
			)
		);

		$template->add_instructions(
			array(
				'Analyze the data and provide a clear, helpful answer to the user\'s question.',
				'Do NOT greet the user or say "Hello" or "How can I assist you".',
				'The user has already asked a specific question - answer it directly with the data provided.',
				'Start your response by directly addressing their question.',
				'If the user asked "how many", "count", or requested a number, you MUST include the exact numeric value.',
				'Use the numbers provided in "KEY DATA FROM TOOLS" or extract them from the tool response JSON.',
				'NEVER say generic phrases like "there are orders" - ALWAYS include the specific number.',
				'If the count is 0, say "There are 0 completed orders" explicitly.',
				'If the user asks to list product tags or categories (e.g., "Show me product tags"), list the names only. Do NOT include counts and do NOT include labels like "Count:". Only include counts if the user explicitly asked for a count/number/how many.',
			)
		);

		$template->add_examples(
			array(
				array(
					'input'  => 'How many completed orders?',
					'output' => 'There are 50 completed orders in the WooCommerce store.',
				),
				array(
					'input'  => 'Show me total revenue',
					'output' => 'The total revenue is $12,450.00.',
				),
			)
		);

		return $template;
	}

	/**
	 * Get predefined template for system role (initial setup).
	 *
	 * @return self
	 */
	public static function system_analyst() {
		$template = new self(
			'You are a helpful WooCommerce data analyst. The system has already run internal tools to fetch real data from the WooCommerce store database when needed. You see the user\'s question and any relevant data in structured JSON form and must reason about that data.',
			array()
		);

		$template->add_instructions(
			array(
				'When the user asks about store data (orders, products, customers, sales, revenue, inventory, stock, etc.), assume the backend has already fetched the relevant data for you and provided it in JSON form in the previous messages.',
				'Never claim you are calling tools or APIs yourself. Instead, clearly reference and interpret the data that has been provided to you.',
				'If the user asks for a chart, graph, pie chart, bar chart, or visualization, use the provided data to explain trends, breakdowns, and comparisons.',
				'The frontend will automatically render charts based on the data and question - you do NOT need to generate charts yourself.',
				'For "sales by product category" or "revenue by category" (e.g. pie chart of sales by product category), the data may include a category_breakdown structure with category names, revenue, and order counts. Use that breakdown to compare categories and explain which ones contribute most or least.',
				'Just provide the data in a clear format.',
				'Do NOT say "I cannot generate charts" or "charts are not yet supported" - charts are handled automatically by the system.',
				'Analyze the user\'s question and base your answer strictly on the data you have been given. If there is no data or only partial data, say so explicitly.',
			)
		);

		return $template;
	}

	/**
	 * Intent parsing template (LLM must output STRICT JSON only).
	 *
	 * @return self
	 */
	public static function intent_parser() {
		$template = new self(
			'You are an intent parser for a WooCommerce analytics assistant.\n\n' .
			'Your task: Convert the user question into a STRICT JSON object matching the schema below. Output ONLY JSON. No markdown, no backticks, no explanations.\n\n' .
			'SCHEMA (intent_version = \"1\"):\n' .
			'{\n' .
			'  \"intent_version\": \"1\",\n' .
			'  \"requires_data\": true|false,\n' .
			'  \"entity\": \"orders\"|\"products\"|\"customers\"|\"categories\"|\"tags\"|\"coupons\"|\"refunds\"|\"stock\"|\"inventory\",\n' .
			'  \"operation\": \"list\"|\"statistics\"|\"by_period\"|\"sample\",\n' .
			'  \"metrics\": [\"total_revenue\"|\"total_orders\"|\"avg_order_value\"|\"unique_customers\"|\"top_products\"],\n' .
			'  \"dimensions\": [\"status\"|\"category\"|\"customer\"|\"day\"|\"week\"|\"month\"|\"hour\"],\n' .
			'  \"filters\": {\n' .
			'    \"date_range\": {\"from\": \"YYYY-MM-DD\"|null, \"to\": \"YYYY-MM-DD\"|null, \"preset\": \"today\"|\"yesterday\"|\"this_week\"|\"last_week\"|\"this_month\"|\"last_month\"|\"this_year\"|\"last_year\"|\"last_quarter\"|null},\n' .
			'    \"status\": string|null,\n' .
			'    \"limit\": integer|null,\n' .
			'    \"sort_by\": \"total_spent\"|\"order_count\"|null,\n' .
			'    \"group_by\": \"customer\"|\"category\"|null,\n' .
			'    \"min_orders\": integer|null,\n' .
			'    \"stock_status\": \"instock\"|\"outofstock\"|\"onbackorder\"|null,\n' .
			'    \"stock_threshold\": integer|null\n' .
			'  },\n' .
			'  \"confidence\": \"low\"|\"medium\"|\"high\",\n' .
			'  \"draft_answer\": string|null\n' .
			'}\n\n' .
			'RULES:\n' .
			'- If the question mentions WooCommerce entities like orders/products/customers/categories/tags/coupons/refunds/stock/inventory, then requires_data MUST be true.\n' .
			'- If the question is NOT asking for WooCommerce data (e.g., weather), set requires_data=false and set entity=\"orders\" and operation=\"list\" as safe defaults.\n' .
			'- Use presets for relative time like \"this month\" when possible.\n' .
			'- For questions like \"in the last 30 days\" or \"last 7 days\", set date_range.preset to \"last_30_days\" / \"last_7_days\" and set from/to to null.\n' .
			'- For \"last quarter\", set date_range.preset to \"last_quarter\" and set from/to to null.\n' .
			'- Revenue questions: entity=orders, operation=statistics, metrics includes total_revenue.\n' .
			'- \"Sales by category\": entity=orders, operation=statistics, dimensions includes category.\n' .
			'- \"Out of stock\": entity=stock, filters.stock_status=\"outofstock\".\n' .
			'- \"Low stock\": entity=stock, filters.stock_threshold=10 unless the user provided a number.\n' .
			'- Discounts/coupons questions (e.g., \"What discounts are currently active?\" / \"Show me coupons\"): entity=coupons, operation=list.\n' .
			'- Coupon usage questions (e.g., \"coupons used in the last month\"): entity=coupons, operation=statistics. If the question says \"last month\", use date_range.preset=\"last_month\".\n' .
			'- Tag count questions (e.g., \"How many products have the tag \\\"New Arrival\\\"?\"): entity=tags, operation=list.\n' .
			'- Product category listing (e.g., \"What categories do my products belong to?\"): entity=categories, operation=list.\n' .
			'- \"Top customers\" or \"total spend by customers\": entity=customers, operation=statistics, filters.sort_by=\"total_spent\", filters.group_by=\"customer\". If question says \"last year\", use date_range.preset=\"last_year\".\n' .
			'- draft_answer can be a brief best-effort sentence, but MUST NOT claim to fetch data or mention tools.\n\n' .
			'User question: \"{question}\"',
			array( 'question' => '' )
		);

		return $template;
	}

	/**
	 * Get predefined template for error handling.
	 *
	 * @return self
	 */
	public static function error_handling() {
		$template = new self(
			'IMPORTANT: If any internal tool or data response indicates an error (for example, "error": true in the JSON you see), politely inform the user that the requested feature or data is not currently available.',
			array()
		);

		$template->add_instructions(
			array(
				'Use the error message and any suggestions included in the data to guide your answer.',
				'If the data indicates that a feature request has been or can be submitted (for example via a "submission_prompt" or similar field), clearly explain this to the user and summarize what was or will be requested on their behalf.',
				'Do NOT claim that you are directly calling any feature-request tools yourself; simply describe, in natural language, what the system has done or can do.',
			)
		);

		return $template;
	}

	/**
	 * Get predefined template for chart requests.
	 *
	 * @return self
	 */
	public static function chart_request() {
		$template = new self(
			'CRITICAL: If the user asked for a chart, graph, pie chart, bar chart, or visualization, DO NOT say that charts are not supported or not yet available.',
			array()
		);

		$template->add_instructions(
			array(
				'Charts are automatically rendered by the frontend - you just need to provide the data.',
				'Simply present the data you fetched in a clear format without mentioning chart limitations.',
			)
		);

		return $template;
	}

	/**
	 * Get predefined template for empty data responses.
	 *
	 * @return self
	 */
	public static function empty_data() {
		$template = new self(
			'If the data shows empty arrays or no results, inform the user that there are currently no records matching their query in the WooCommerce store database.',
			array()
		);

		return $template;
	}

	/**
	 * Combine multiple templates into one prompt.
	 *
	 * @param array $templates Array of template instances or strings.
	 * @return string Combined prompt.
	 */
	public static function combine( array $templates ) {
		$parts = array();
		foreach ( $templates as $template ) {
			if ( $template instanceof self ) {
				$parts[] = $template->format();
			} elseif ( is_string( $template ) ) {
				$parts[] = $template;
			}
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * Get structured output schema for OpenAI function calling.
	 * This ensures consistent response formats.
	 *
	 * @param string $format_type Type of structured output ('analysis', 'summary', 'count').
	 * @return array Schema array for OpenAI API.
	 */
	public static function get_structured_output_schema( $format_type = 'analysis' ) {
		$schemas = array(
			'analysis' => array(
				'type'       => 'object',
				'properties' => array(
					'answer'      => array(
						'type'        => 'string',
						'description' => 'The main answer to the user\'s question',
					),
					'data_points' => array(
						'type'        => 'array',
						'description' => 'Key data points extracted from the analysis',
						'items'       => array(
							'type' => 'string',
						),
					),
					'confidence'  => array(
						'type'        => 'number',
						'description' => 'Confidence level (0-1) in the answer',
					),
				),
				'required'   => array( 'answer' ),
			),
			'summary'  => array(
				'type'       => 'object',
				'properties' => array(
					'summary'     => array(
						'type'        => 'string',
						'description' => 'Brief summary of the data',
					),
					'total_count' => array(
						'type'        => 'integer',
						'description' => 'Total count if applicable',
					),
					'key_metrics' => array(
						'type'        => 'object',
						'description' => 'Key metrics extracted from the data',
					),
				),
				'required'   => array( 'summary' ),
			),
			'count'    => array(
				'type'       => 'object',
				'properties' => array(
					'count'  => array(
						'type'        => 'integer',
						'description' => 'The exact count/number requested',
					),
					'entity' => array(
						'type'        => 'string',
						'description' => 'The entity type being counted',
					),
				),
				'required'   => array( 'count', 'entity' ),
			),
		);

		return isset( $schemas[ $format_type ] ) ? $schemas[ $format_type ] : $schemas['analysis'];
	}

	/**
	 * Add structured output instruction to template.
	 *
	 * @param string $format_type Type of structured output.
	 * @return self
	 */
	public function with_structured_output( $format_type = 'analysis' ) {
		$schema = self::get_structured_output_schema( $format_type );
		$this->add_instruction(
			'IMPORTANT: Format your response as structured JSON matching this schema: ' . wp_json_encode( $schema )
		);
		return $this;
	}
}

