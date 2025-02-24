<?php

$l10n = array(
	'queue_limit_exceeded' => 'You have attempted to queue too many files.',
	'file_exceeds_size_limit' => 'This file exceeds the maximum upload size for this site.',
	'zero_byte_file' => 'This file is empty. Please try another.',
	'invalid_filetype' => 'This file type is not allowed. Please try another.',
	'default_error' => 'An error occurred in the upload. Please try again later.',
	'missing_upload_url' => 'There was a configuration error. Please contact the server administrator.',
	'upload_limit_exceeded' => 'You may only upload 1 file.',
	'http_error' => 'HTTP error.',
	'upload_failed' => 'Upload failed.',
	'io_error' => 'IO error.',
	'security_error' => 'Security error.',
	'file_cancelled' => 'File canceled.',
	'upload_stopped' => 'Upload stopped.',
	'dismiss' => 'Dismiss',
	'crunching' => 'Crunching&hellip;',
	'deleted' => 'moved to the trash.',
	'error_uploading' => 'has failed to upload due to an error',
	'cancel_upload' => 'Cancel upload',
	'dismiss' => 'Dismiss'
);

?>
<script type="text/javascript">
	var plugin_url = '<?php echo PMXI_ROOT_URL; ?>';
	var swfuploadL10n = <?php echo json_encode($l10n); ?>;
</script>
<table class="layout pmxi_step_1">
	<tr>
		<td class="left">
			<h2><?php _e('Choose A XML or CSV file', 'pmxi_plugin') ?></h2>
			<h3><?php _e('', 'pmxi_plugin') ?></h3>

			<?php if ($this->errors->get_error_codes()): ?>
				<?php $this->error() ?>
			<?php endif ?>
			<?php
				if ( ! $reimported_import->isEmpty()):
				?>
					<div class="reimported_notify">
						<p><?php _e( 'You are importing a new file for: <b>' . $reimported_import->name . '</b>' , 'pmxi_plugin' );?></p>
						<p><span><?php _e( 'Last imported on ' . date("m-d-Y H:i:s", strtotime($reimported_import->registered_on)) , 'pmxi_plugin' ); ?></span></p>
					</div>
				<?php
				endif;
			?>
			<?php do_action('pmxi_choose_file_header'); ?>
	        <form method="post" class="choose-file enter-submit" enctype="multipart/form-data" autocomplete="off">
				<input type="hidden" name="is_submitted" value="1" />
				<?php wp_nonce_field('upload-xml', '_wpnonce_upload-xml') ?>
				<?php if (!empty($detection_feed_extension)):?>
				<div class="file-type-container">
					<h3>
						<input type="radio" id="type_ftp" name="type" value="url" <?php echo ('url' == $post['type'] and $detection_feed_extension) ? 'checked="checked"' : '' ?> />
						<label for="type_ftp"><?php _e('Select feed extension manually', 'pmxi_plugin'); ?></label>
					</h3>
					<div class="file-type-options">
						<input type="text" class="regular-text" name="url" value="<?php echo esc_attr($post['url']) ?>" />
						<select name="feed_type">
							<option value="xml" <?php echo ('xml' == $post['feed_type']) ? 'checked="checked"' : ''; ?>>XML</option>
							<option value="csv" <?php echo ('csv' == $post['feed_type']) ? 'checked="checked"' : ''; ?>>CSV</option>
							<option value="zip" <?php echo ('zip' == $post['feed_type']) ? 'checked="checked"' : ''; ?>>ZIP</option>
							<option value="gz" <?php echo ('gz' == $post['feed_type']) ? 'checked="checked"' : ''; ?>>GZ</option>
						</select>
					</div>
				</div>
				<?php else:?>
				<div class="file-type-container">
					<h3>
						<input type="radio" id="type_upload" name="type" value="upload" <?php echo 'upload' == $post['type'] ? 'checked="checked"' : '' ?> />
						<label for="type_upload"><?php _e('Upload File From Your Computer', 'pmxi_plugin') ?></label>
					</h3>
					<div id="plupload-ui" class="file-type-options">
			            <div>
			   			<p><strong>Please upgrade to the $5 version to use this feature.</strong> <br> You can upgrade here: <a href="http://plugin-boutique.com/import-anything/">http://plugin-boutique.com/import-anything/</a></p>
			            </div>
			        </div>
				</div>
				<div class="file-type-container">
					<h3>
						<input type="radio" id="type_url" name="type" value="url" <?php echo ('url' == $post['type']) ? 'checked="checked"' : '' ?> />
						<label for="type_url"><?php _e('Use File From Website URL', 'pmxi_plugin') ?></label>
					</h3>
					<div class="file-type-options">
						<input type="text" class="regular-text" name="url" value="<?php echo esc_attr($post['url']) ?>" />
					</div>
				</div>
				<?php if (PMXI_Plugin::getInstance()->getOption('enable_ftp_import')):?>
				<div class="file-type-container">
					<h3>
						<input type="radio" id="type_ftp" name="type" value="ftp" <?php echo 'ftp' == $post['type'] ? 'checked="checked"' : '' ?> />
						<label for="type_ftp"><?php _e('Download File(s) From FTP Server', 'pmxi_plugin') ?></label>
					</h3>
					<div class="file-type-options">
						<input type="text" class="regular-text" name="ftp[url]" value="<?php echo esc_attr($post['ftp']['url']) ?>" /><br />
						<input type="text" name="ftp[user]" title="username" style='width: 150px;' /><strong>:</strong><input type="password" name="ftp[pass]" title="passowrd" style='width: 150px;' />
						<div class="note"><?php _e('<b>Troubleshooting:</b> Use the full URL to the file on the FTP server. Make sure if you paste the URL into your browser and enter the username and password, the file you are attempting to import downloads. You may use an asterisk to load multiple files. For example, ftp://example.com/datafeeds/*.xml', 'pmxi_plugin') ?></div>
					</div>
				</div>
				<?php endif; ?>
				<div class="file-type-container">
					<h3>
						<input type="radio" id="type_file" name="type" value="file" <?php echo 'file' == $post['type'] ? 'checked="checked"' : '' ?> />
						<label for="type_file"><?php _e('Use file from FTP', 'pmxi_plugin') ?></label>
					</h3>
					<div class="file-type-options">
						<input type="text" id="__FILE_SOURCE" class="regular-text autocomplete" name="file" value="<?php echo esc_attr($post['file']) ?>" />
						<?php
							$local_files = array_merge(
								PMXI_Helper::safe_glob(PMXI_Plugin::ROOT_DIR . '/upload/*.xml', PMXI_Helper::GLOB_RECURSE),
								PMXI_Helper::safe_glob(PMXI_Plugin::ROOT_DIR . '/upload/*.gz', PMXI_Helper::GLOB_RECURSE),
								PMXI_Helper::safe_glob(PMXI_Plugin::ROOT_DIR . '/upload/*.zip', PMXI_Helper::GLOB_RECURSE),
								PMXI_Helper::safe_glob(PMXI_Plugin::ROOT_DIR . '/upload/*.csv', PMXI_Helper::GLOB_RECURSE)
							);
							sort($local_files);
						?>
						<script type="text/javascript">
							__FILE_SOURCE = <?php echo json_encode($local_files) ?>;
						</script>
						<div class="note"><?php printf(__('Upload files to <strong>%s</strong> and they will appear in this list', 'pmxi_plugin'), PMXI_Plugin::ROOT_DIR . '/upload/') ?></div>
					</div>
				</div>
				<div id="url_upload_status"></div>
				<?php endif; ?>				
				<p class="submit-buttons">
					<input type="hidden" name="is_submitted" value="1" />
					<?php wp_nonce_field('choose-file', '_wpnonce_choose-file') ?>
					<input type="submit" class="button button-primary button-hero large_button" value="<?php _e('Next', 'pmxi_plugin') ?>" id="advanced_upload"/>
				</p>
				<br />
				<table><tr><td class="note"></td></tr></table>
			</form>
		</td>
		<td class="right">
			&nbsp;
		</td>
	</tr>
</table>
