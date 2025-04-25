<div class="wrap">    
    <h2><?php _e('Starmaker ID List', 'dd-fraud'); ?></h2>
      <div id="starmaker-id">			
          <div id="nds-post-body">		
            <form id="starmaker_id" method="get">
              <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
              <?php
                $this->listings_table->views();
                $this->listings_table->search_box( __( 'Find', 'dd-fraud' ), 'dd-starmaker-search');
                $this->listings_table->display(); 
              ?>
            </form>
          </div>			
      </div>
</div>