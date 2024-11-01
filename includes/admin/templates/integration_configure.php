<?php add_thickbox(); ?>
<div class="wc_pricewaiter_setup_wrap">
	Hello, you are on your way to selling more. It's very exicting.

	<?php if ( !self::has_configured( 'wc_api_user' ) ) : ?>
	<div id="wc_pricewaiter_setup_step_1" class="wc_pricewaiter_setup_step">
		<div class="wc_pricewaiter_setup_step_num"><h3><span>1)</span> Enable WooCommerce REST API</h3></div>
		<div class="wc_pricewaiter_setup_step_content">
			<p>In order for PriceWaiter to automate things for you, we need access to the WooCommerce REST API.<br><em>Please setup one of the options below.</em></p>

			<div class="js_error_holder"></div>

			<div class="wc_pricewaiter_setup_block_unique">
				<h4>Create a unique API user <em>(recommended)</em>:</h4>
				<p>
					<label for="wc_pricewaiter_api_user_login">Username:</label><br>
					<input type="text" name="wc_pricewaiter_api_user_login" id="wc_pricewaiter_api_user_login" value="pricewaiterapi">
				</p>
				<p>
					<label for="wc_pricewaiter_api_user_email">Email:</label><br>
					<input type="email" name="wc_pricewaiter_api_user_email" id="wc_pricewaiter_api_user_email" placeholder="uniqueaddress@domain.com">
				</p>
				<p class="submit">
					<button class="button-primary" name="save_wc_pricewaiter_setup_api_user_new" id="save_wc_pricewaiter_setup_api_user_new">Create API User</button>
				</p>
			</div>

			<div class="wc_pricewaiter_setup_block_current">
				<h4>Grant API access to existing user:</h4>
				<select name="wc_pricewaiter_api_user_existing" id="wc_pricewaiter_api_user_existing">
					<option value="">Select administrator</option>
					<?php foreach ($admin_users as $admin_user) : ?>
					<option value="<?php echo $admin_user->ID; ?>"><?php echo $admin_user->user_login; ?> (<?php echo $admin_user->user_email; ?>)</option>
					<?php endforeach; ?>
				</select>
				<p class="submit">
					<button class="button-primary" name="save_wc_pricewaiter_setup_api_user_existing" id="save_wc_pricewaiter_setup_api_user_existing">Grant Access</button>
				</p>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( !self::has_configured( 'pw_api_key' ) && self::has_configured( 'wc_api_user' ) ) : ?>
	<div id="wc_pricewaiter_setup_step_2" class="wc_pricewaiter_setup_step">
		<div class="wc_pricewaiter_setup_step_num"><h3><span>2)</span> Create Your PriceWaiter Account</h3></div>
		<div class="wc_pricewaiter_setup_step_content">
			<p><strong>Use one of these links to create your PriceWaiter account.</strong> (<em>They will help prefill some of your store settings</em>).
			<p>
				Sign up for a <a href="<?php echo self::get_sign_up_url(null, array('tier' => 'free')); ?>&TB_iframe=true&width=600&height=550" class="thickbox button-primary">FREE Account</a> or <a href="<?php echo self::get_sign_up_url(null, array('tier' => 'premium')); ?>&TB_iframe=true&width=600&height=550" class="thickbox button-primary">Premium Account</a> 
				Not sure which one to pick? View our <a href="<?php echo self::get_sign_up_url('https://www.pricewaiter.com/pricing',array('new_signup' => 1)); ?>" target="_blank">plan comparision</a> page.
			</p>
			<hr>
			<p>If you already have an account, just enter your store API key below and click "Save Changes".</p>
		</div>
	</div>
	<?php endif; ?>
</div>
