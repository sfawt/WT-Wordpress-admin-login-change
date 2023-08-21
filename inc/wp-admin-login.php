<?php defined('ABSPATH') or die();
if ( defined( 'ABSPATH' ) && ! class_exists( 'wt_admin_change_login' ) ) {
	

	class wt_admin_change_login {
		private $wp_login_php;

		private function basename() {
			return plugin_basename( __FILE__ );
		}

		private function path() {
			return trailingslashit( dirname( __FILE__ ) );
		}

		private function use_trailing_slashes() {
			return '/' === substr( get_option( 'permalink_structure' ), -1, 1 );
		}

		private function user_trailingslashit( $string ) {
			return $this->use_trailing_slashes() ? trailingslashit( $string ) : untrailingslashit( $string );
		}

		private function wp_template_loader() {
			global $pagenow;

			$pagenow = 'index.php';

			if ( ! defined( 'WP_USE_THEMES' ) ) {
				define( 'WP_USE_THEMES', true );
			}

			wp();

			if ( $_SERVER['REQUEST_URI'] === $this->user_trailingslashit( str_repeat( '-/', 10 ) ) ) {
				$_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/wp-login-php/' );
			}

			require_once( ABSPATH . WPINC . '/template-loader.php' );

			die;
		}

		private function new_login_slug() {
			if (
				( $slug = get_option( 'rwl_page' ) ) || (
					is_multisite() &&
					is_plugin_active_for_network( $this->basename() ) &&
					( $slug = get_site_option( 'rwl_page', 'login' ) )
				) ||
				( $slug = 'login' )
			) {
				return $slug;
			}
		}

		public function new_login_url( $scheme = null ) {
			if ( get_option( 'permalink_structure' ) ) {
				return $this->user_trailingslashit( home_url( '/', $scheme ) . $this->new_login_slug() );
			} else {
				return home_url( '/', $scheme ) . '?' . $this->new_login_slug();
			}
		}

		public function __construct() {
			global $wp_version;

			if ( version_compare( $wp_version, '4.0-RC1-src', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notices_incompatible' ) );
				add_action( 'network_admin_notices', array( $this, 'admin_notices_incompatible' ) );

				return;
			}

			register_activation_hook( $this->basename(), array( $this, 'activate' ) );
			register_uninstall_hook( $this->basename(), array( 'wt_admin_change_login', 'uninstall' ) );

			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );

			if ( is_multisite() && ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			}

			add_filter( 'plugin_action_links_' . $this->basename(), array( $this, 'plugin_action_links' ) );

			if ( is_multisite() && is_plugin_active_for_network( $this->basename() ) ) {
				add_filter( 'network_admin_plugin_action_links_' . $this->basename(), array( $this, 'plugin_action_links' ) );

				add_action( 'wpmu_options', array( $this, 'wpmu_options' ) );
				add_action( 'update_wpmu_options', array( $this, 'update_wpmu_options' ) );
			}

			add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 1 );
			add_action( 'wp_loaded', array( $this, 'wp_loaded' ) );

			add_filter( 'site_url', array( $this, 'site_url' ), 10, 4 );
			add_filter( 'network_site_url', array( $this, 'network_site_url' ), 10, 3 );
			add_filter( 'wp_redirect', array( $this, 'wp_redirect' ), 10, 2 );

			add_filter( 'site_option_welcome_email', array( $this, 'welcome_email' ) );

			remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
		}

		public function admin_notices_incompatible() {
			echo '<div class="error"><p>' . sprintf( __( 'Etkinleştirmek için lütfen WordPress\'in en son sürümüne yükseltin %s.', 'wt-login-change' ), '<strong>' . __( 'wp-admin URL değiştirme', 'wt-login-change' ) . '</strong>' ) . '</p></div>';
		}

		public function activate() {
			add_option( 'rwl_redirect', '1' );
			delete_option( 'rwl_admin' );
		}

		public static function uninstall() {
			global $wpdb;

			if ( is_multisite() ) {
				$blogs = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

				if ( $blogs ) {
					foreach ( $blogs as $blog ) {
						switch_to_blog( $blog );
						delete_option( 'rwl_page' );
					}

					restore_current_blog();
				}

				delete_site_option( 'rwl_page' );
			} else {
				delete_option( 'rwl_page' );
			}
		}


		public function wpmu_options() {
			$out = '';

			$out .= '<h3>' . __( 'Giriş URL\'nizi değiştirin.', 'wt-login-change' ) . '</h3>';
			$out .= '<p>' . __( 'Bu seçenek, ağ genelinde geçerli bir varsayılan belirlemenizi sağlar, ki bu bireysel siteler tarafından geçersiz kılınabilir. Sadece sitedeki kalıcı bağlantı ayarlarına giderek URL\'yi değiştirebilirsiniz..', 'wt-login-change' ) . '</p>';
			$out .= '<table class="form-table">';
				$out .= '<tr valign="top">';
					$out .= '<th scope="row">' . __( 'Ağ genelinde geçerli olan varsayılan ayar.', 'wt-login-change' ) . '</th>';
					$out .= '<td><input id="rwl-page-input" type="text" name="rwl_page" value="' . get_site_option( 'rwl_page', 'giris' )  . '"></td>';
				$out .= '</tr>';
			$out .= '</table>';

			echo $out;
		}

		public function update_wpmu_options() {
			if (
				( $rwl_page = sanitize_title_with_dashes( $_POST['rwl_page'] ) ) &&
				strpos( $rwl_page, 'wp-login' ) === false &&
				! in_array( $rwl_page, $this->forbidden_slugs() )
			) {
				update_site_option( 'rwl_page', $rwl_page );
			}
		}

		public function admin_init() {
			global $pagenow;

			add_settings_section(
				'wt-login-change-section',
				__( 'Giriş URL\'nizi değiştirin.', 'wt-login-change' ),
				array( $this, 'rwl_section_desc' ),
				'permalink'
			);

			add_settings_field(
				'rwl-page',
				'<label for="rwl-page">' . __( 'Admin URL', 'wt-login-change' ) . '</label>',
				array( $this, 'rwl_page_input' ),
				'permalink',
				'wt-login-change-section'
			);

			// Add redirect field
			add_settings_field(
				'rwl_redirect_field', __( 'Hatalı URL', 'wt-login-change' ),
				array( $this, 'rwl_redirect_func' ),
				'permalink',
				'wt-login-change-section'
			);


			register_setting( 'permalink','rwl_page_input');
			register_setting( 'permalink','rwl_redirect_field');

			if (current_user_can('manage_options') && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'update-permalink')) {
				if( isset($_POST['permalink_structure']) && isset( $_POST['rwl_redirect_field'] ) ){
					$short_domain = sanitize_title_with_dashes(wp_unslash( $_POST['rwl_redirect_field'] ));
					update_option( 'rwl_redirect_field', $short_domain );
			}

			if( isset($_POST['permalink_structure']) && isset( $_POST['rwl_page'] ) ){
				if (
				( $rwl_page = sanitize_title_with_dashes( $_POST['rwl_page'] ) ) &&
				strpos( $rwl_page, 'wp-login' ) === false &&
				! in_array( $rwl_page, $this->forbidden_slugs() )
				) {
					if ( is_multisite() && $rwl_page === get_site_option( 'rwl_page', 'login' ) ) {
						delete_option( 'rwl_page' );
					} else {
						update_option( 'rwl_page', $rwl_page );
					}
				}
			}

			if ( get_option( 'rwl_redirect' ) ) {
					delete_option( 'rwl_redirect' );

					if ( is_multisite() && is_super_admin() && is_plugin_active_for_network( $this->basename() ) ) {
					$redirect = network_admin_url( 'settings.php#rwl-page-input' );
					} else {
					$redirect = admin_url( 'options-permalink.php#rwl-page-input' );
					}

					wp_safe_redirect( $redirect );

					die;
					}
			}

		}

		public function rwl_section_desc() {
			$out = '';

			if ( is_multisite() && is_super_admin() && is_plugin_active_for_network( $this->basename() ) ) {
				$out .= '<p>' . sprintf( __( 'To set a networkwide default, go to %s.', 'wt-login-change' ), '<a href="' . network_admin_url( 'settings.php#rwl-page-input' ) . '">' . __( 'Network Settings', 'wt-login-change' ) . '</a>') . '</p>';

			}
			echo $out;
		}
		public function rwl_page_input() {
			if ( get_option( 'permalink_structure' ) ) {
				echo '<code>' . trailingslashit( home_url() ) . '</code> <input id="rwl-page-input" type="text" name="rwl_page" value="' . $this->new_login_slug()  . '">' . ( $this->use_trailing_slashes() ? ' <code>/</code>' : '' );
			} else {
				echo '<code>' . trailingslashit( home_url() ) . '?</code> <input id="rwl-page-input" type="text" name="rwl_page" value="' . $this->new_login_slug()  . '">';
			}
			echo '<p class="description" id="giris_url"><strong>' . __( 'Bu alanı boş bırakmanız wp-admin olarak açılacaktır.', 'wt-login-change' ) . '</strong></p>';
		}
		public function rwl_redirect_func() {
			$value = get_option( 'rwl_redirect_field' );
			echo '<code>' . trailingslashit( home_url() ) . '</code> <input type="text" value="' . esc_attr( $value ) . '" name="rwl_redirect_field" id="rwl_redirect_field" class="regular-text" /> <code>/</code>';
			echo '<p class="description"><strong>' . __( 'Bu alanı boş bırakmanız durumunda anasayfanıza yönlenecektir.', 'wt-login-change' ) . '</strong></p>';
		}

		

		public function admin_notices() {
			global $pagenow;

			if ( ! is_network_admin() && $pagenow === 'options-permalink.php' && isset( $_GET['settings-updated'] ) ) {
				echo '<div class="updated"><p>' . sprintf( __( 'Your login page is now here: %s. Bookmark this page!', 'wt-login-change' ), '<strong><a href="' . $this->new_login_url() . '">' . $this->new_login_url() . '</a></strong>' ) . '</p></div>';
			}
		}

		public function plugin_action_links( $links ) {
			if ( is_network_admin() && is_plugin_active_for_network( $this->basename() ) ) {
				array_unshift( $links, '<a href="' . network_admin_url( 'settings.php#rwl-page-input' ) . '">' . __( 'Settings', 'wt-login-change' ) . '</a>' );
			} elseif ( ! is_network_admin() ) {
				array_unshift( $links, '<a href="' . admin_url( 'options-permalink.php#rwl-page-input' ) . '">' . __( 'Settings', 'wt-login-change' ) . '</a>' );
			}

			return $links;
		}


		public function plugins_loaded() {

			global $pagenow;

			if ( ! is_multisite()
			     && ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-signup' ) !== false
			          || strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-activate' ) !== false ) ) {

				wp_die( __( 'This feature is not enabled.', 'wt-login-change' ) );

			}

			$request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

			if ( ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-login.php' ) !== false
			       || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-login', 'relative' ) ) )
			     && ! is_admin() ) {

				$this->wp_login_php = true;

				$_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/' . str_repeat( '-/', 10 ) );

				$pagenow = 'index.php';

			} elseif ( ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === home_url( $this->new_login_slug(), 'relative' ) )
			           || ( ! get_option( 'permalink_structure' )
			                && isset( $_GET[ $this->new_login_slug() ] )
			                && empty( $_GET[ $this->new_login_slug() ] ) ) ) {

				$pagenow = 'wp-login.php';

			} elseif ( ( strpos( rawurldecode( $_SERVER['REQUEST_URI'] ), 'wp-register.php' ) !== false
			             || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-register', 'relative' ) ) )
			           && ! is_admin() ) {

				$this->wp_login_php = true;

				$_SERVER['REQUEST_URI'] = $this->user_trailingslashit( '/' . str_repeat( '-/', 10 ) );

				$pagenow = 'index.php';
			}

		}


		public function wp_loaded() {
			global $pagenow;

			if ( is_admin() && ! is_user_logged_in() && ! defined( 'DOING_AJAX' ) ) {

				if ( get_option( 'rwl_redirect_field' ) == 'false' ) {
				  wp_safe_redirect( '/' );
				} else {
					wp_safe_redirect( '/' . get_option( 'rwl_redirect_field' ) );
				}

				die();
				//wp_die( __( 'You must log in to access the admin area.', 'wt-login-change' ) );
			}

			$request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );

			if (
				$pagenow === 'wp-login.php' &&
				$request['path'] !== $this->user_trailingslashit( $request['path'] ) &&
				get_option( 'permalink_structure' )
			) {
				wp_safe_redirect( $this->user_trailingslashit( $this->new_login_url() ) . ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . $_SERVER['QUERY_STRING'] : '' ) );
				die;
			} elseif ( $this->wp_login_php ) {
				if (
					( $referer = wp_get_referer() ) &&
					strpos( $referer, 'wp-activate.php' ) !== false &&
					( $referer = parse_url( $referer ) ) &&
					! empty( $referer['query'] )
				) {
					parse_str( $referer['query'], $referer );

					if (
						! empty( $referer['key'] ) &&
						( $result = wpmu_activate_signup( $referer['key'] ) ) &&
						is_wp_error( $result ) && (
							$result->get_error_code() === 'already_active' ||
							$result->get_error_code() === 'blog_taken'
					) ) {
						wp_safe_redirect( $this->new_login_url() . ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . $_SERVER['QUERY_STRING'] : '' ) );
						die;
					}
				}

				$this->wp_template_loader();
			} elseif ( $pagenow === 'wp-login.php' ) {
				global $error, $interim_login, $action, $user_login;

				@require_once ABSPATH . 'wp-login.php';

				die;
			}
		}

		public function site_url( $url, $path, $scheme, $blog_id ) {
			return $this->filter_wp_login_php( $url, $scheme );
		}

		public function network_site_url( $url, $path, $scheme ) {
			return $this->filter_wp_login_php( $url, $scheme );
		}

		public function wp_redirect( $location, $status ) {
			return $this->filter_wp_login_php( $location );
		}

		public function filter_wp_login_php( $url, $scheme = null ) {
			$current_url = isset( $_SERVER['PHP_SELF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) : '';
			if ( is_int( strpos( $url, 'wp-login.php' ) ) || is_int( strpos( $url, 'wp-login' ) ) ) {
				if ( is_ssl() ) {
					$scheme = 'https';
				}
				$args = explode( '?', $url );
				if ( isset( $args[1] ) ) {
					wp_parse_str( $args[1], $args );
					$url = add_query_arg( $args, $this->new_login_url( $scheme ) );
				} else {
					$url = $this->new_login_url( $scheme );
				}
			}

			if ( ! is_int( strpos( $current_url, 'wp-admin' ) ) ) {
				return $url;
			}

			if( ! function_exists( 'is_user_logged_in' ) ) {
				return $url;
			}

			if ( ! is_user_logged_in() ) {
				$redirect_url = get_option( 'rwl_redirect_field', '/' );
				if ( is_null( $redirect_url ) ) {
					$redirect_url = '';
				}
				return '/' . $redirect_url;
			}

			return $url;
		}

		public function welcome_email( $value ) {
			return $value = str_replace( 'wp-login.php', trailingslashit( get_site_option( 'rwl_page', 'login' ) ), $value );
		}

		public function forbidden_slugs() {
			$wp = new WP;
			return array_merge( $wp->public_query_vars, $wp->private_query_vars );
		}
	}

	new wt_admin_change_login;
}
