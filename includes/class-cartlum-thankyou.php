<?php
/**
 * Thank-you page sections: on the phone-first checkout's thank-you page the store chooses the order
 * of its sections (the confirmation, order details, buttons, a video, "follow us" links and its own
 * text) and which to show. On WooCommerce's own thank-you page the video, the links and the text
 * appear above the order details.
 *
 * @package Cartlum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cartlum_Thankyou.
 */
class Cartlum_Thankyou {

	/**
	 * Sections in their first order. The confirmation always shows (the offer after the order and
	 * payment instructions go with it).
	 */
	const SECTIONS = array( 'message', 'details', 'buttons', 'video', 'follow', 'text' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'cartlum_settings_after_fields', array( __CLASS__, 'editor' ), 10, 2 );
		add_filter( 'cartlum_settings_save', array( __CLASS__, 'save' ), 10, 3 );
		add_action( 'woocommerce_before_thankyou', array( __CLASS__, 'classic' ), 5 );
	}

	/**
	 * Section names.
	 *
	 * @return array key => label
	 */
	public static function labels() {
		return array(
			'message' => __( 'Your order is confirmed (with your message)', 'cartlum' ),
			'details' => __( 'Order details', 'cartlum' ),
			'buttons' => __( 'Buttons: Continue shopping, WhatsApp', 'cartlum' ),
			'video'   => __( 'Video', 'cartlum' ),
			'follow'  => __( 'Follow us links', 'cartlum' ),
			'text'    => __( 'Your own text', 'cartlum' ),
		);
	}

	/**
	 * The sections in the store's order.
	 *
	 * @return bool[] key => shown
	 */
	public static function layout() {
		$out = array();
		foreach ( explode( ',', (string) Cartlum_Settings::get( 'thankyou_layout' ) ) as $part ) {
			$part = trim( $part );
			$key  = ltrim( $part, '-' );
			if ( in_array( $key, self::SECTIONS, true ) && ! isset( $out[ $key ] ) ) {
				$out[ $key ] = 'message' === $key || '-' !== substr( $part, 0, 1 );
			}
		}
		foreach ( self::SECTIONS as $key ) {
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = true;
			}
		}
		return $out;
	}

	/**
	 * "Follow us" links: one per line, "Name | address".
	 *
	 * @return string[] name => URL
	 */
	private static function links() {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) Cartlum_Settings::get( 'thankyou_follow' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] && preg_match( '#^https?://#i', $parts[1] ) ) {
				$out[ $parts[0] ] = $parts[1];
			}
		}
		return $out;
	}

	/**
	 * One of the shared sections (video, follow us, own text), or '' when it has nothing to show.
	 *
	 * @param string $key   Section.
	 * @param string $class Box class: clx-card on the phone-first page.
	 * @return string
	 */
	public static function section( $key, $class = 'cartlum-ty' ) {
		$inner = '';
		if ( 'video' === $key ) {
			$url = trim( (string) Cartlum_Settings::get( 'thankyou_video' ) );
			if ( '' !== $url ) {
				$embed = wp_oembed_get( $url, array( 'width' => 640 ) );
				$inner = $embed ? '<div class="cartlum-ty__video">' . $embed . '</div>' : '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Watch the video', 'cartlum' ) . '</a></p>';
			}
		} elseif ( 'follow' === $key ) {
			$links = self::links();
			if ( $links ) {
				$inner = '<h2 class="clx-card__title">' . esc_html__( 'Follow us', 'cartlum' ) . '</h2><p class="cartlum-ty__links">';
				foreach ( $links as $name => $url ) {
					$inner .= '<a class="clx-btn clx-btn--ghost" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $name ) . '</a> ';
				}
				$inner .= '</p>';
			}
		} elseif ( 'text' === $key ) {
			$text = trim( (string) Cartlum_Settings::get( 'thankyou_text' ) );
			if ( '' !== $text ) {
				$inner = wpautop( esc_html( $text ) );
			}
		}
		return '' === $inner ? '' : '<section class="' . esc_attr( $class ) . ' cartlum-ty--' . esc_attr( $key ) . '">' . $inner . '</section>';
	}

	/**
	 * WooCommerce's own thank-you page: the video, links and text above the order details.
	 *
	 * @param int $order_id Order.
	 */
	public static function classic( $order_id ) {
		unset( $order_id );
		$html = '';
		foreach ( self::layout() as $key => $shown ) {
			if ( $shown && in_array( $key, array( 'video', 'follow', 'text' ), true ) ) {
				$html .= self::section( $key );
			}
		}
		if ( '' !== $html ) {
			echo '<style>.cartlum-ty{margin:0 0 1.5em}.cartlum-ty .clx-card__title{font-size:1.1em;margin:0 0 .5em}.cartlum-ty__links a{display:inline-block;margin:0 .5em .5em 0}.cartlum-ty__video iframe{max-width:100%}</style>' . $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped in section(); a video is WordPress's own oEmbed HTML.
		}
	}

	/* ---------- Settings ---------- */

	/**
	 * The sections list on Checkout & Buttons → Thank-you page.
	 *
	 * @param string $tab    Tab.
	 * @param array  $values Settings.
	 */
	public static function editor( $tab, $values ) {
		unset( $values );
		if ( 'thankyou' !== $tab ) {
			return;
		}
		$labels = self::labels();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Sections', 'cartlum' ); ?></th>
				<td>
					<p class="description" style="margin-top:0"><?php esc_html_e( 'On the phone-first checkout’s thank-you page: drag to change the order (or use the arrows), untick to hide. A section with nothing filled in is left out by itself. On WooCommerce’s own thank-you page, the video, the links and your text show above the order details.', 'cartlum' ); ?></p>
					<ol class="cl-sort" data-cl-sort style="margin:8px 0 0;padding:0;list-style:none;max-width:560px">
						<?php foreach ( self::layout() as $key => $shown ) : ?>
							<li draggable="true" data-cl-sort-item="<?php echo esc_attr( $key ); ?>" style="display:flex;align-items:center;gap:8px;margin:0 0 6px;padding:6px 8px;border:1px solid #c3c4c7;border-radius:6px;background:#fff;cursor:grab">
								<span aria-hidden="true" style="color:#787c82">&#8942;&#8942;</span>
								<input type="hidden" name="cartlum[thankyou_sections][]" value="<?php echo esc_attr( $key ); ?>">
								<?php if ( 'message' === $key ) : ?>
									<span style="flex:1"><?php echo esc_html( $labels[ $key ] ); ?> <span class="description">(<?php esc_html_e( 'always shown', 'cartlum' ); ?>)</span></span>
								<?php else : ?>
									<label style="flex:1"><input type="checkbox" name="cartlum[thankyou_shown][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $shown ); ?>> <?php echo esc_html( $labels[ $key ] ); ?></label>
								<?php endif; ?>
								<?php /* translators: %s: section */ ?>
								<button type="button" class="button button-small" data-cl-sort-move="-1" aria-label="<?php echo esc_attr( sprintf( __( 'Move up: %s', 'cartlum' ), $labels[ $key ] ) ); ?>">&uarr;</button>
								<?php /* translators: %s: section */ ?>
								<button type="button" class="button button-small" data-cl-sort-move="1" aria-label="<?php echo esc_attr( sprintf( __( 'Move down: %s', 'cartlum' ), $labels[ $key ] ) ); ?>">&darr;</button>
							</li>
						<?php endforeach; ?>
					</ol>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the sections' order.
	 *
	 * @param array  $values Settings.
	 * @param string $tab    Tab.
	 * @param array  $input  Raw input.
	 * @return array
	 */
	public static function save( $values, $tab, $input ) {
		if ( 'thankyou' !== $tab || ! isset( $input['thankyou_sections'] ) || ! is_array( $input['thankyou_sections'] ) ) {
			return $values;
		}
		$shown = isset( $input['thankyou_shown'] ) && is_array( $input['thankyou_shown'] ) ? array_map( 'sanitize_key', $input['thankyou_shown'] ) : array();
		$parts = array();
		foreach ( array_map( 'sanitize_key', $input['thankyou_sections'] ) as $key ) {
			if ( in_array( $key, self::SECTIONS, true ) ) {
				$parts[] = 'message' === $key || in_array( $key, $shown, true ) ? $key : '-' . $key;
			}
		}
		$layout = implode( ',', array_unique( $parts ) );
		// The first order, all shown, stays "not arranged", so saving the tab unchanged changes nothing.
		$values['thankyou_layout'] = implode( ',', self::SECTIONS ) === $layout ? '' : $layout;
		return $values;
	}
}
