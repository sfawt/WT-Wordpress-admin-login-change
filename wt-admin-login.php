<?php
 /*
 Plugin Name: WT Wordpress Admin URL değiştirme
 Plugin URI: https://wt.net.tr/wt-admin-url-degistirme
 Description: Wordpress admin giriş urlnizi değiştirmenizi sağlamaktadır. Değişim işlemi sonrası wp-admin / wp-login.php / admin olarak erişim sağlamanızı engelleyecek ve belirlediğiniz URL ile giriş yapmanızı sağlayacaktır.
 Version: 1.0
 Author: furkanaltintas
 Author URI: https://furkanaltintas.com.tr/
 Text Domain: wt-login-change
 */
require_once 'inc/wp-admin-login.php';
add_filter('plugin_action_links', 'your_plugin_settings_link', 10, 2);

	function your_plugin_settings_link($links, $file) {
		if (plugin_basename(__FILE__) === $file) {
			$settings_link = '<a href="' . admin_url('options-permalink.php#giris_url') . '">Ayarlar</a>';
			array_push($links, $settings_link);
		}
		return $links;
	}