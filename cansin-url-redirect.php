<?php
/*
  Plugin Name: CCA URL Shorten & Redirect
  Description: This plugin is used to conrol the list of redirections for another Wordpress website.
  Version: 1.8
  Author: Cansin Cagan Acarer
  Author URI: https://cacarer.com
 * 
 */



// If a redirect list is requested, check if the requested link matches one in the list; if so, redirect.
function cansin_url_redirect_init() {
	$redirectListJson = file_get_contents( get_option('cansin_url_redirect_client_Address', "") . "?URLRedirectAPISecret=" . get_option('cansin_url_redirect_host_api_secret', "") );
	$redirectListArray = json_decode($redirectListJson, true);
	$requestedLink = substr($_SERVER["REQUEST_URI"], 1);

	if (is_array($redirectListArray)) {
		foreach ($redirectListArray as $key => $value) {
			if ($value['name'] == $requestedLink) {
				wp_redirect( $value['link'] );
				exit;
			}
		}
	}
}
// Hook init if website role is host and client and secret is are set
if ( get_option('cansin_url_redirect_role', "") === "host" && get_option('cansin_url_redirect_client_Address', "") != "" && get_option('cansin_url_redirect_host_api_secret', "") != "" ){
	add_action('init', 'cansin_url_redirect_init');
}


// Options request page
add_action( 'init', 'cansinUrlRedirectAPI' );
function cansinUrlRedirectAPI() {
	// If a request comes from authorized server, respond with the json redirectList.
	if ( isset($_GET['URLRedirectAPISecret']) && $_GET['URLRedirectAPISecret'] == get_option('cansin_url_redirect_client_api_secret', "") ) {
		header('Content-Type: application/json');
		$redirectList = get_option('cansin_url_redirect_list', array());
		$redirectList = json_encode($redirectList);
		echo $redirectList;
		exit();
	}
}

// Settings page
add_action( 'admin_menu', 'cansinUrlRedirectSettings' );
function cansinUrlRedirectSettings() {
    add_options_page( 'URL Redirect List', 'URL Redirect List', 'manage_options', 'cansin-url-redirect-list', 'cansinUrlRedirectSettingsPage' );
}


// Register settings link for the Plugins page
function cansinUrlRedirectClientSettingsLink($links) { 
	$settings_link = '<a href="options-general.php?page=cansin-url-redirect-list">Settings</a>'; 
	array_unshift($links, $settings_link); 
	return $links; 
}
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'cansinUrlRedirectClientSettingsLink' );


// Settings page contents:
function cansinUrlRedirectSettingsPage() {
	$usedOptions = get_option('cansin_url_redirect_list', array());

	// If host website is changed, update the option
	if (isset($_POST['hostWebsiteAddress'])) {
		$hostWebsiteAddress = esc_url_raw( $_POST['hostWebsiteAddress'] );
		update_option('cansin_url_redirect_host_Address', $hostWebsiteAddress);
	}

	// If host API secret is changed, update the option
	if (isset($_POST['hostAPISecret'])) {
		$hostAPISecret = sanitize_text_field( $_POST['hostAPISecret'] );
		update_option('cansin_url_redirect_client_api_secret', $hostAPISecret);
	}

	// If client website is changed, update the option
	if (isset($_POST['clientWebsiteAddress'])) {
		$clientWebsiteAddress = esc_url_raw( $_POST['clientWebsiteAddress'] );
		update_option('cansin_url_redirect_client_Address', $clientWebsiteAddress);
	}

	// If client API secret is changed, update the option
	if (isset($_POST['clientAPISecret'])) {
		$clientAPISecret = sanitize_text_field( $_POST['clientAPISecret'] );
		update_option('cansin_url_redirect_host_api_secret', $clientAPISecret);
	}

	// If website role is changed, update the option
	if (isset($_POST['cansin_url_redirect_role'])) {
		$websiteRole = sanitize_text_field( $_POST['cansin_url_redirect_role'] );
		update_option('cansin_url_redirect_role', $websiteRole);
	}

	// If a shortlink is deleted
	if (isset($_POST['url_redirect_delete'])) {
		$usedOptions = get_option('cansin_url_redirect_list', array());
		foreach ($usedOptions as $key => $value) {
		   if ($value['name'] == $_POST['url_redirect_delete']) {
			  unset($usedOptions[$key]);
		   }
		}
		update_option('cansin_url_redirect_list', $usedOptions);
	 }

	// If a new link is added, update the option
	if ( isset($_POST['url_redirect_name']) && isset($_POST['url_redirect_link']) && $_POST['url_redirect_name'] != "" && $_POST['url_redirect_link'] != "") {
		
		$name = sanitize_title($_POST['url_redirect_name']);
		$link = esc_url_raw($_POST['url_redirect_link'], 'http');
		$save = TRUE;

		// If shortlink is known, update the link
		foreach ($usedOptions as $key => $value) {
		   if ($value['name'] == $name) {
			  $value['link'] = $link;

			  $usedOptions[$key] = $value;

			  $save = FALSE;
		   }
		}

		// If shortlink is new, add it to the array and save it
		if ($save) {
		   $usedOptions[] = array(
			   'name' => $name,
			   'link' => $link,
			   'click' => 0
		   );
		}
		update_option('cansin_url_redirect_list', $usedOptions);
	}

	// Show even if settings are empty
		?>
		<div class="wrap">
			<h2>URL Redirection Settings</h2>
			<hr>
			<?php
			// Show only if website role is empty
			if ( get_option('cansin_url_redirect_role', "") === "" ) {
				?>
					<form action="#" method="post">
						<h2>Plugin Settings</h2>
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="cansin_url_redirect_role">How do you want to setup this website</label></th>
									<td>
										<fieldset>
											<label>
												<input type="radio" name="cansin_url_redirect_role" value="client" <?php if (get_option('cansin_url_redirect_role', "") === "client") { echo 'checked="checked"'; }?>> <span>As the <strong>client</strong> website</span>
											</label>
											<p>URL redirections will be made using this domain.</p>
											<br>
											<label>
												<input type="radio" name="cansin_url_redirect_role" value="host" <?php if (get_option('cansin_url_redirect_role', "") === "host") { echo 'checked="checked"'; }?>> <span>As the <strong>host</strong> website</span>
											</label>
											<p>URL redirections will be controlled on this website.</p>
										</fieldset>
									</td>
								</tr>
							</tbody>
						</table>
						<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
					</form>
				<?php
			} else { 
				if ( get_option('cansin_url_redirect_role', "") === "client" ) {
				?>
					<script>
						var $ = jQuery.noConflict();
						$(function() {
							// Edit button function
							$('button.edit').click(function(){
								$('#url_redirect_name').val($(this).data('name'));
								$('#url_redirect_link').val($(this).data('link'));
							});
						});
						function deleteLink($toBeDeleted) {
							var shortLink = $('#redirectList tbody tr:nth-child(' + $toBeDeleted + ') td:nth-child(2)').text();
							if ( confirm ("Are you sure you want the delete the shortlink: " + shortLink + "?") ) {
								$('#delete' + $toBeDeleted).submit();

							}
						}
					</script>
					<?php
					// Show only if not empty
					if ( get_option('cansin_url_redirect_host_Address', "") !== "" && get_option('cansin_url_redirect_client_api_secret', "") !== "" ) {
						?>
						<section class="panel">
							<h2>Add a redirection</h2>
							<p>
								<form id="redirectionForm" class="form-inline" method="post" action="#"> 
									<div class="input-group">
										<span class="input-group-addon"><?php echo get_option('cansin_url_redirect_host_Address', ""); ?>/</span>
										<input placeholder="short link" class="form-control" type="text" required name="url_redirect_name" id="url_redirect_name" />
										<input placeholder="target address" class="form-control" type="text" required name="url_redirect_link" id="url_redirect_link" />
										<input type="hidden" id="url_redirect_submit" name="submit"  value="Save" />
										<button class="button" type="submit" onclick="$('#redirectionForm').submit()"> Save</button>
									</div>
								</form>
							</p>
						</section>
						<section class="panel" style="overflow-x: auto;">
							<table class="widefat" id="redirectList">
								<thead>
									<tr>
										<th>Order</th>
										<th>Short Link</th>
										<th>Destination Link</th>
										<th>Options</th>
									</tr>
								</thead>
								<tbody><?php
									$i = 0;
									foreach ($usedOptions as $row) :
										$i++;
										?>
											<tr>
												<td style="text-align: center;"><?php echo $i ?></td>
												<td><a href="<?php echo get_option('cansin_url_redirect_host_Address', ""); ?>/<?php echo $row['name']; ?>" target="_blank"><?php echo get_option('cansin_url_redirect_host_Address', ""); ?>/<?php echo $row['name']; ?></a></td>
												<td><a href="<?php echo $row['link']; ?>" style=" display: block; max-width: 500px; " target="_blank"><?php echo $row['link']; ?></a></td>
												<td style="min-width: 100px;">
													<button title="Edit" class="button edit" type="submit" data-name="<?php echo $row['name']; ?>" data-link="<?php echo $row['link']; ?>">
														Edit
													</button>
													<button title="Remove" class="button" onclick="deleteLink(<?php echo $i ?>)">
														Remove
													</button>
													<form id="delete<?php echo $i ?>" class="url_redirect_delete_form" method="POST" action="#" style="display: inline">
														<input type="hidden" value="<?php echo $row['name']; ?>" name="url_redirect_delete"/>
													</form>
													<form id="reset<?php echo $i ?>" class="url_redirect_reset_form" method="POST" action="#" style="display: inline">
														<input type="hidden" value="<?php echo $row['name']; ?>" name="url_redirect_reset"/>
													</form>
												</td>
											</tr>
										<?php
									endforeach; ?>
								</tbody>
							</table>
							<hr>
						</section>
						<?php 
					} // Show the rest even if the client settings are empty ?>
					<form action="#" method="post">
						<h2>Plugin Settings</h2>
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="cansin_url_redirect_role">Host Website Address</label></th>
									<td>
										<input name="hostWebsiteAddress"  value="<?php echo get_option('cansin_url_redirect_host_Address', ""); ?>" type="text" placeholder="https://example.com" class="regular-text">
										<p class="description">Enter the host website where the redirections will be made.</p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="blogdescription">API Secret</label></th>
									<td><input name="hostAPISecret"  value="<?php echo get_option('cansin_url_redirect_client_api_secret', ""); ?>" type="text" placeholder="Enter a code for keeping your redirect list secure." class="regular-text">
										<p class="description">Enter a code for keeping your redirect list secure.</p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="cansin_url_redirect_role">How do you want to setup this website</label></th>
									<td>
										<fieldset>
											<label>
												<input type="radio" name="cansin_url_redirect_role" value="client" <?php if (get_option('cansin_url_redirect_role', "") === "client") { echo 'checked="checked"'; }?>> <span>As the <strong>client</strong> website</span>
											</label>
											<p>URL redirections will be made using this domain.</p>
											<br>
											<label>
												<input type="radio" name="cansin_url_redirect_role" value="host" <?php if (get_option('cansin_url_redirect_role', "") === "host") { echo 'checked="checked"'; }?>> <span>As the <strong>host</strong> website</span>
											</label>
											<p>URL redirections will be controlled on this website.</p>
										</fieldset>
									</td>
								</tr>
							</tbody>
						</table>
						<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
					</form>
					<?php 
				} // Close if: Site role is client
				elseif ( get_option('cansin_url_redirect_role', "") === "host" ) {
					?>
						<form action="#" method="post">
							<h2>Plugin Settings</h2>
							<table class="form-table" role="presentation">
								<tbody>
									<tr>
										<th scope="row"><label for="cansin_url_redirect_role">Client Website Address</label></th>
										<td>
											<input name="clientWebsiteAddress"  value="<?php echo get_option('cansin_url_redirect_client_Address', ""); ?>" type="text" placeholder="https://example.com" class="regular-text">
											<p class="description">Enter the client website where the redirections will be made.</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="blogdescription">API Secret</label></th>
										<td><input name="clientAPISecret"  value="<?php echo get_option('cansin_url_redirect_host_api_secret', ""); ?>" type="text" placeholder="Enter a code for keeping your redirect list secure." class="regular-text">
											<p class="description">Enter a code for keeping your redirect list secure.</p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="cansin_url_redirect_role">How do you want to setup this website</label></th>
										<td>
											<fieldset>
												<legend class="screen-reader-text"><span>Date Format</span></legend>
												<label>
													<input type="radio" name="cansin_url_redirect_role" value="client" <?php if (get_option('cansin_url_redirect_role', "") === "client") { echo 'checked="checked"'; }?>> <span>As the <strong>client</strong> website</span>
												</label>
												<p>URL redirections will be made using this domain.</p>
												<br>
												<label>
													<input type="radio" name="cansin_url_redirect_role" value="host" <?php if (get_option('cansin_url_redirect_role', "") === "host") { echo 'checked="checked"'; }?>> <span>As the <strong>host</strong> website</span>
												</label>
												<p>URL redirections will be controlled on this website.</p>
											</fieldset>
										</td>
									</tr>
									<?php 
										// If options are not completed, as the use to complete
										if( get_option('cansin_url_redirect_client_Address', "")=="" || get_option('cansin_url_redirect_host_api_secret', "")=="" ){
											echo "Please fill out the settings below.";
										}
										else {
											// Redirection list address
											?>
												<p class="description">The redirect list will be retrieved from: <code><?php echo get_option('cansin_url_redirect_client_Address', "") . "?URLRedirectAPISecret=" . get_option('cansin_url_redirect_host_api_secret', ""); ?></code>.</p>
											<?php 
										}
									?>
								</tbody>
							</table>
							<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
						</form>
					<?php					
				} // Close if: Site role is host
			} // Close else: Site role is set ?>
		</div>
	<?php
}

