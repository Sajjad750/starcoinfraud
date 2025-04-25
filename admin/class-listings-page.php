<?php
/**
 * Creates the listing page for the plugin.
 *
 * Provides the functionality necessary for rendering the page corresponding
 * to the submenu with which this page is associated.
 * @package Dd_Fraud_Prevention
 */

defined( 'ABSPATH' ) || exit;
 
class Listings_Page {

  private $listings_table;
 
  public function render() {
    $this->listings_table->prepare_items();
    include_once( 'views/listings.php' );
  }

  public function load_screen_options()
  {
    $this->listings_table = new Listings_Table();
  }

  public function get_types()
  {
      $types = array(
          'starmaker_id' => "Starmaker ID",
          'email' => "Email",
          'customer_name' => "Name",
          'ip' => "IP Address"
      );

      return $types;
  }
}