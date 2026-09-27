<?php
/**
 * Plugin Name: Price Scale Slider
 * Description: Interactive sliding-scale pricing with two plans, volume tiers and a currency selector. Use the [price_scale] shortcode or the "Price Scale" block.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Kai
 * License: GPL-2.0-or-later
 * Text Domain: price-scale-slider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PSS_OPTION  = 'pss_settings';
const PSS_VERSION = '1.0.0';

/** Settings used until the site owner saves their own. Everything here is editable in Settings > Price Scale. */
function pss_defaults() {
	return array(
		'title'      => 'Choose your team size',
		'unit_label' => 'users',
		'min'        => 1,
		'max'        => 200,
		'step'       => 1,
		'default'    => 25,
		'accent'     => '#2f6fdf',
		'plans'      => array(
			array(
				'name'     => 'Standard',
				'base'     => 0,
				'tiers'    => "1:12\n21:10\n51:8\n101:6",
				'features' => "Core features\nEmail support",
				'cta_text' => 'Get Standard',
				'cta_url'  => '#',
			),
			array(
				'name'     => 'Pro',
				'base'     => 49,
				'tiers'    => "1:18\n21:15\n51:12\n101:9",
				'features' => "Everything in Standard\nPriority support\nAdvanced reports",
				'cta_text' => 'Get Pro',
				'cta_url'  => '#',
			),
		),
		'currencies' => "EUR|€|1\nDKK|kr.|7.46\nUSD|$|1.08\nGBP|£|0.85",
		'period'     => 'per month',
	);
}

function pss_settings() {
	$saved = get_option( PSS_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), pss_defaults() );
}

/**
 * Turns the editable text fields into the config the front end uses.
 * Tiers are "from_quantity:unit_price" lines (the price per unit once the quantity reaches `from`);
 * currencies are "CODE|symbol|rate" lines where rate converts from the base currency (first line).
 */
function pss_public_config( $s ) {
	$plans = array();
	foreach ( (array) $s['plans'] as $plan ) {
		$tiers = array();
		foreach ( preg_split( '/\R/', (string) $plan['tiers'] ) as $line ) {
			$parts = array_map( 'trim', explode( ':', $line ) );
			if ( count( $parts ) === 2 && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) ) {
				$tiers[] = array( 'from' => (float) $parts[0], 'price' => (float) $parts[1] );
			}
		}
		usort( $tiers, function ( $a, $b ) {
			return $a['from'] <=> $b['from'];
		} );
		$plans[] = array(
			'name'     => (string) $plan['name'],
			'base'     => (float) $plan['base'],
			'tiers'    => $tiers,
			'features' => array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $plan['features'] ) ) ) ),
			'ctaText'  => (string) $plan['cta_text'],
			'ctaUrl'   => esc_url_raw( (string) $plan['cta_url'] ),
		);
	}
	$currencies = array();
	foreach ( preg_split( '/\R/', (string) $s['currencies'] ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line ) );
		if ( count( $parts ) === 3 && $parts[0] !== '' && is_numeric( $parts[2] ) ) {
			$currencies[] = array( 'code' => strtoupper( $parts[0] ), 'symbol' => $parts[1], 'rate' => (float) $parts[2] );
		}
	}
	return array(
		'title'      => (string) $s['title'],
		'unitLabel'  => (string) $s['unit_label'],
		'min'        => (float) $s['min'],
		'max'        => (float) $s['max'],
		'step'       => (float) $s['step'],
		'value'      => (float) $s['default'],
		'period'     => (string) $s['period'],
		'plans'      => $plans,
		'currencies' => $currencies ? $currencies : array( array( 'code' => 'EUR', 'symbol' => '€', 'rate' => 1 ) ),
	);
}

// ---------------------------------------------------------------- front end

function pss_register_assets() {
	$url = plugin_dir_url( __FILE__ );
	wp_register_style( 'pss', $url . 'assets/pss.css', array(), PSS_VERSION );
	wp_register_script( 'pss', $url . 'assets/pss.js', array(), PSS_VERSION, true );
	wp_register_script( 'pss-block', $url . 'assets/pss-block.js', array( 'wp-blocks', 'wp-element', 'wp-server-side-render' ), PSS_VERSION, true );
}
add_action( 'init', 'pss_register_assets' );

function pss_render() {
	$s = pss_settings();
	wp_enqueue_style( 'pss' );
	wp_enqueue_script( 'pss' );
	$config = wp_json_encode( pss_public_config( $s ) );
	return sprintf(
		'<div class="pss" style="--pss-accent:%1$s" data-pss-config="%2$s"><noscript>%3$s</noscript></div>',
		esc_attr( sanitize_hex_color( $s['accent'] ) ? $s['accent'] : '#2f6fdf' ),
		esc_attr( $config ),
		esc_html__( 'Enable JavaScript to see pricing.', 'price-scale-slider' )
	);
}
add_shortcode( 'price_scale', 'pss_render' );

function pss_register_block() {
	register_block_type( 'pss/price-scale', array(
		'editor_script'   => 'pss-block',
		'render_callback' => 'pss_render',
	) );
}
add_action( 'init', 'pss_register_block', 20 );

// ---------------------------------------------------------------- settings page

function pss_admin_menu() {
	add_options_page( 'Price Scale', 'Price Scale', 'manage_options', 'price-scale', 'pss_settings_page' );
}
add_action( 'admin_menu', 'pss_admin_menu' );

function pss_register_setting() {
	register_setting( 'pss', PSS_OPTION, array( 'sanitize_callback' => 'pss_sanitize' ) );
}
add_action( 'admin_init', 'pss_register_setting' );

function pss_sanitize( $in ) {
	$d   = pss_defaults();
	$out = array(
		'title'      => sanitize_text_field( $in['title'] ?? $d['title'] ),
		'unit_label' => sanitize_text_field( $in['unit_label'] ?? $d['unit_label'] ),
		'min'        => (float) ( $in['min'] ?? $d['min'] ),
		'max'        => (float) ( $in['max'] ?? $d['max'] ),
		'step'       => max( 0.0001, (float) ( $in['step'] ?? $d['step'] ) ),
		'default'    => (float) ( $in['default'] ?? $d['default'] ),
		'accent'     => sanitize_hex_color( $in['accent'] ?? '' ) ? $in['accent'] : $d['accent'],
		'period'     => sanitize_text_field( $in['period'] ?? $d['period'] ),
		'currencies' => sanitize_textarea_field( $in['currencies'] ?? $d['currencies'] ),
		'plans'      => array(),
	);
	if ( $out['max'] <= $out['min'] ) {
		$out['max'] = $out['min'] + $out['step'];
	}
	$out['default'] = min( max( $out['default'], $out['min'] ), $out['max'] );
	for ( $i = 0; $i < 2; $i++ ) {
		$p                = $in['plans'][ $i ] ?? $d['plans'][ $i ];
		$out['plans'][ $i ] = array(
			'name'     => sanitize_text_field( $p['name'] ?? '' ),
			'base'     => (float) ( $p['base'] ?? 0 ),
			'tiers'    => sanitize_textarea_field( $p['tiers'] ?? '' ),
			'features' => sanitize_textarea_field( $p['features'] ?? '' ),
			'cta_text' => sanitize_text_field( $p['cta_text'] ?? '' ),
			'cta_url'  => esc_url_raw( $p['cta_url'] ?? '' ),
		);
	}
	return $out;
}

function pss_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = pss_settings();
	$n = PSS_OPTION;
	?>
	<div class="wrap">
		<h1>Price Scale</h1>
		<p>Place the slider with the <code>[price_scale]</code> shortcode or the <strong>Price Scale</strong> block.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'pss' ); ?>
			<h2>Slider</h2>
			<table class="form-table" role="presentation">
				<tr><th><label>Heading</label></th><td><input class="regular-text" name="<?php echo esc_attr( $n ); ?>[title]" value="<?php echo esc_attr( $s['title'] ); ?>"></td></tr>
				<tr><th><label>Unit label</label></th><td><input name="<?php echo esc_attr( $n ); ?>[unit_label]" value="<?php echo esc_attr( $s['unit_label'] ); ?>"> <span class="description">e.g. users, seats, GB</span></td></tr>
				<tr><th>Range</th><td>
					min <input type="number" step="any" name="<?php echo esc_attr( $n ); ?>[min]" value="<?php echo esc_attr( $s['min'] ); ?>" style="width:90px">
					max <input type="number" step="any" name="<?php echo esc_attr( $n ); ?>[max]" value="<?php echo esc_attr( $s['max'] ); ?>" style="width:90px">
					step <input type="number" step="any" name="<?php echo esc_attr( $n ); ?>[step]" value="<?php echo esc_attr( $s['step'] ); ?>" style="width:90px">
					start at <input type="number" step="any" name="<?php echo esc_attr( $n ); ?>[default]" value="<?php echo esc_attr( $s['default'] ); ?>" style="width:90px">
				</td></tr>
				<tr><th><label>Price period</label></th><td><input name="<?php echo esc_attr( $n ); ?>[period]" value="<?php echo esc_attr( $s['period'] ); ?>"> <span class="description">shown after the price, e.g. "per month"</span></td></tr>
				<tr><th><label>Accent colour</label></th><td><input type="color" name="<?php echo esc_attr( $n ); ?>[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>"></td></tr>
				<tr><th><label>Currencies</label></th><td>
					<textarea rows="5" cols="40" name="<?php echo esc_attr( $n ); ?>[currencies]"><?php echo esc_textarea( $s['currencies'] ); ?></textarea>
					<p class="description">One per line: <code>CODE|symbol|rate</code>. The first line is the base currency (rate 1); prices below are entered in it.</p>
				</td></tr>
			</table>
			<?php foreach ( array( 0, 1 ) as $i ) : $p = $s['plans'][ $i ]; $pn = $n . '[plans][' . $i . ']'; ?>
			<h2>Plan <?php echo (int) $i + 1; ?></h2>
			<table class="form-table" role="presentation">
				<tr><th>Name</th><td><input name="<?php echo esc_attr( $pn ); ?>[name]" value="<?php echo esc_attr( $p['name'] ); ?>"></td></tr>
				<tr><th>Fixed fee</th><td><input type="number" step="any" name="<?php echo esc_attr( $pn ); ?>[base]" value="<?php echo esc_attr( $p['base'] ); ?>"> <span class="description">added once on top of the per-unit price</span></td></tr>
				<tr><th>Price per unit</th><td>
					<textarea rows="5" cols="30" name="<?php echo esc_attr( $pn ); ?>[tiers]"><?php echo esc_textarea( $p['tiers'] ); ?></textarea>
					<p class="description">One tier per line: <code>from:price</code>. <code>21:10</code> means every unit costs 10 once the slider reaches 21.</p>
				</td></tr>
				<tr><th>Features</th><td><textarea rows="4" cols="40" name="<?php echo esc_attr( $pn ); ?>[features]"><?php echo esc_textarea( $p['features'] ); ?></textarea><p class="description">One per line.</p></td></tr>
				<tr><th>Button</th><td><input name="<?php echo esc_attr( $pn ); ?>[cta_text]" value="<?php echo esc_attr( $p['cta_text'] ); ?>"> <input class="regular-text" name="<?php echo esc_attr( $pn ); ?>[cta_url]" value="<?php echo esc_attr( $p['cta_url'] ); ?>" placeholder="https://"></td></tr>
			</table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
