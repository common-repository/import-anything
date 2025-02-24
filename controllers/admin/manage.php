<?php 
/**
 * Manage Imports
 * 
 * 
 */
class PMXI_Admin_Manage extends PMXI_Controller_Admin {
	
	public function init() {
		parent::init();
		
		if ('update' == PMXI_Plugin::getInstance()->getAdminCurrentScreen()->action) {
			$this->isInline = true;			
		}
	}
	
	/**
	 * Previous Imports list
	 */
	public function index() {
		
		$get = $this->input->get(array(
			's' => '',
			'order_by' => 'registered_on',
			'order' => 'DESC',
			'pagenum' => 1,
			'perPage' => 25,
		));
		$get['pagenum'] = absint($get['pagenum']);
		extract($get);
		$this->data += $get;
		
		$list = new PMXI_Import_List();
		$post = new PMXI_Post_Record();
		$by = array('parent_import_id' => 0);
		if ('' != $s) {
			$like = '%' . preg_replace('%\s+%', '%', preg_replace('/[%?]/', '\\\\$0', $s)) . '%';
			$by[] = array(array('name LIKE' => $like, 'type LIKE' => $like, 'path LIKE' => $like, 'friendly_name LIKE' => $like), 'OR');
		}
		
		$this->data['list'] = $list->join($post->getTable(), $list->getTable() . '.id = ' . $post->getTable() . '.import_id', 'LEFT')
			->setColumns(
				$list->getTable() . '.*',
				'COUNT(' . $post->getTable() . '.post_id' . ') AS post_count'
			)
			->getBy($by, "$order_by $order", $pagenum, $perPage, $list->getTable() . '.id');
			
		$this->data['page_links'] = paginate_links(array(
			'base' => add_query_arg('pagenum', '%#%', $this->baseUrl),
			'format' => '',
			'prev_text' => __('&laquo;', 'pmxi_plugin'),
			'next_text' => __('&raquo;', 'pmxi_plugin'),
			'total' => ceil($list->total() / $perPage),
			'current' => $pagenum,
		));
		
		pmxi_session_unset();		

		$this->render();
	}
	
	/**
	 * Edit Template
	 */
	public function edit() {
		
		pmxi_session_unset();

		// deligate operation to other controller
		$controller = new PMXI_Admin_Import();
		$controller->set('isTemplateEdit', true);
		$controller->template();
	}
	
	/**
	 * Edit Options
	 */
	public function options() {

		pmxi_session_unset();
		
		// deligate operation to other controller
		$controller = new PMXI_Admin_Import();
		$controller->set('isTemplateEdit', true);
		$controller->options();
	}

	/**
	 * Cron Scheduling
	 */
	public function scheduling() {
		$this->data['id'] = $id = $this->input->get('id');
		$this->data['cron_job_key'] = PMXI_Plugin::getInstance()->getOption('cron_job_key');
		$this->data['item'] = $item = new PMXI_Import_Record();
		if ( ! $id or $item->getById($id)->isEmpty()) {
			wp_redirect($this->baseUrl); die();
		}

		$this->render();
	}
	
	/**
	 * Reimport
	 */
	public function update() {
		$id = $this->input->get('id');
		$action_type = $this->input->get('type');

		$this->data['item'] = $item = new PMXI_Import_Record();
		if ( ! $id or $item->getById($id)->isEmpty()) {
			wp_redirect($this->baseUrl); die();
		}				
		
		pmxi_session_unset();

		$chunks = 0;

		if ($this->input->post('is_confirmed')) {

			check_admin_referer('update-import', '_wpnonce_update-import');		
		
			$uploads = wp_upload_dir();

			if (in_array($item->type, array('ftp')) and empty(PMXI_Plugin::$session->data['pmxi_import']['chunk_number'])) {
				
				// path to remote file
				$remote_file = $item->path;
								
				// set up basic connection
				$ftp_url = $item->path;
				$parsed_url = parse_url($ftp_url);
				$ftp_server = $parsed_url['host'] ;
				$conn_id = ftp_connect( $ftp_server );
				$is_ftp_ok = TRUE;				

				// login with username and password
				$ftp_user_name = rawurldecode($parsed_url['user']);
				$ftp_user_pass = rawurldecode($parsed_url['pass']);

				// hide warning message
				echo '<span style="display:none">';
				if ( !ftp_login($conn_id, $ftp_user_name, $ftp_user_pass) ){
					$this->errors->add('form-validation', __('Login authentication failed', 'pmxi_plugin'));
					$is_ftp_ok = false;
				}
				echo '</span>';

				if ( $is_ftp_ok ){						

					$files = (strpos($item->path, "*") !== false) ? PMXI_Helper::safe_glob($item->path, PMXI_Helper::GLOB_NODIR | PMXI_Helper::GLOB_PATH) : array($item->path);
					$local_paths = array();

					if ($files) {
						foreach ($files as $singlePath) {

							if (preg_match('%\W(xml|csv|txt|dat|psv|gz|zip)$%i', trim($singlePath))){

								$parsed_url = parse_url($singlePath);						

								$local_file = $uploads['path']  .'/'. basename($parsed_url['path']);										
								$local = @fopen($local_file,"w"); 
								$result = @ftp_fget($conn_id, $local, $parsed_url["path"], FTP_BINARY); 

								if (!$result) { 
									$c = curl_init($singlePath);
									// $local is the location to store file on local machine
									$fh = fopen($local_file, 'w') or $this->errors->add('form-validation', __('There was a problem while downloading ' . $singlePath . ' to ' . $local_file, 'pmxi_plugin'));
									curl_setopt($c, CURLOPT_FILE, $fh);
									curl_exec($c);
									curl_close($c);							
								}						

								$local_paths[] = $local_file;
							}
						}
						
						foreach ($local_paths as $key => $path) {

							if ( preg_match('%\W(gz)$%i', $path)){
								
								$fileInfo = pmxi_gzfile_get_contents($path);
								if ( ! is_wp_error($fileInfo)){
									$local_paths[$key] = $fileInfo['localPath'];
								}
								else $this->errors->add('form-validation', $fileInfo->get_error_message());
							}
							elseif ( preg_match('%\W(zip)$%i', $path) ){

								include_once(PMXI_Plugin::ROOT_DIR.'/libraries/pclzip.lib.php');

								$archive = new PclZip($path);
							    if (($v_result_list = $archive->extract(PCLZIP_OPT_PATH, $uploads['path'], PCLZIP_OPT_REPLACE_NEWER)) == 0) {
							    	$this->errors->add('form-validation', 'Failed to open uploaded ZIP archive : '.$archive->errorInfo(true));			    	
							   	}
								else {
									
									$localPath = '';

									if (!empty($v_result_list)){
										foreach ($v_result_list as $unzipped_file) {											
											if ($unzipped_file['status'] == 'ok' and preg_match('%\W(xml|csv|txt|dat|psv)$%i', trim($unzipped_file['stored_filename']))) { $localPath = $unzipped_file['filename']; break; }	
										}
									}
							    	if($uploads['error']){
										 $this->errors->add('form-validation', __('Can not create upload folder. Permision denied', 'pmxi_plugin'));
									}

									if(empty($localPath)){						
										$zip = zip_open(trim($path));
										if (is_resource($zip)) {														
											while ($zip_entry = zip_read($zip)) {
												$localPath = zip_entry_name($zip_entry);												
											    $fp = fopen($uploads['path']."/".$localPath, "w");
											    if (zip_entry_open($zip, $zip_entry, "r")) {
											      $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
											      fwrite($fp,"$buf");
											      zip_entry_close($zip_entry);
											      fclose($fp);
											    }
											    break;
											}
											zip_close($zip);							

										} else {
									        $this->errors->add('form-validation', __('Failed to open uploaded ZIP archive. Can\'t extract files.', 'pmxi_plugin'));
									    }						
									}																								

									$local_paths[$key] = $localPath;												
								}
							}

							if ( preg_match('%\W(csv|txt|dat|psv)$%i', trim($local_paths[$key])) or (!empty($fileInfo) and $fileInfo['type'] == 'csv') ){																																					
								include_once(PMXI_Plugin::ROOT_DIR.'/libraries/XmlImportCsvParse.php');		
								$csv = new PMXI_CsvParser($local_paths[$key], true, '', ( ! empty($item->options['delimiter']) ) ? $item->options['delimiter'] : '', ( ! empty($item->options['encoding']) ) ? $item->options['encoding'] : ''); // create chunks
								$local_paths[$key] = $csv->xml_path;																	
							}
							
						}

					} else $filePath = '';
					
					// close the connection and the file handler
					ftp_close($conn_id);										

				}		
				
			} 

			if ( empty(PMXI_Plugin::$session->data['pmxi_import']['chunk_number']) ) {			
				
				if ($item->type == 'url'){
					
					if ('zip' == $item->feed_type or '' == $item->feed_type and preg_match('%\W(zip)$%i', trim($item->path))) {							
					
						$tmpname = $uploads['path'] . '/' . wp_unique_filename($uploads['path'], basename($item->path));

						@copy($item->path, $tmpname);				
						
						if (!file_exists($tmpname)) {
							
							$request = get_file_curl($item->path, $tmpname);

							if ( is_wp_error($request) ) $this->errors->add('form-validation', $request->get_error_message());
						    if (!file_exists($tmpname)) $this->errors->add('form-validation', __('Failed upload ZIP archive', 'pmxi_plugin'));
						
						}						

						include_once(PMXI_Plugin::ROOT_DIR.'/libraries/pclzip.lib.php');

						$archive = new PclZip($tmpname);
					    if (($v_result_list = $archive->extract(PCLZIP_OPT_PATH, $uploads['path'], PCLZIP_OPT_REPLACE_NEWER)) == 0) {
					    	$this->errors->add('form-validation', 'Failed to open uploaded ZIP archive : '.$archive->errorInfo(true));			    	
					   	}
						else {
							
							$filePath = '';

							if (!empty($v_result_list)){
								foreach ($v_result_list as $unzipped_file) {									
									if ($unzipped_file['status'] == 'ok' and preg_match('%\W(xml|csv|txt|dat|psv)$%i', trim($unzipped_file['stored_filename']))) { $filePath = $unzipped_file['filename']; break; }	
								}
							}
					    	if($uploads['error']){
								 $this->errors->add('form-validation', __('Can not create upload folder. Permision denied', 'pmxi_plugin'));
							}

							if(empty($filePath)){						
								$zip = zip_open(trim($tmpname));
								if (is_resource($zip)) {																		
									while ($zip_entry = zip_read($zip)) {
										$filePath = zip_entry_name($zip_entry);												
									    $fp = fopen($uploads['path']."/".$filePath, "w");
									    if (zip_entry_open($zip, $zip_entry, "r")) {
									      $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
									      fwrite($fp,"$buf");
									      zip_entry_close($zip_entry);
									      fclose($fp);
									    }
									    break;
									}
									zip_close($zip);							

								} else {
							        $this->errors->add('form-validation', __('Failed to open uploaded ZIP archive. Can\'t extract files.', 'pmxi_plugin'));
							    }						
							}													

							if (preg_match('%\W(csv|txt|dat|psv)$%i', trim($filePath))){																																
								include_once(PMXI_Plugin::ROOT_DIR.'/libraries/XmlImportCsvParse.php');
								$csv = new PMXI_CsvParser($filePath, true, '', ( ! empty($item->options['delimiter']) ) ? $item->options['delimiter'] : '', ( ! empty($item->options['encoding']) ) ? $item->options['encoding'] : '' ); // create chunks
								@unlink($filePath);
								$filePath = $csv->xml_path;									   					  									
							}							
						}
						
					} elseif ('csv' == $item->feed_type or '' == $item->feed_type and preg_match('%\W(csv|txt|dat|psv)$%i', trim($item->path))) {
														
						// copy remote file in binary mode
						$filePath = pmxi_copy_url_file($item->path);									
						if ( ! is_wp_error($filePath) ){
							include_once(PMXI_Plugin::ROOT_DIR.'/libraries/XmlImportCsvParse.php');					
							$csv = new PMXI_CsvParser($filePath, true, '', ( ! empty($item->options['delimiter']) ) ? $item->options['delimiter'] : '', ( ! empty($item->options['encoding']) ) ? $item->options['encoding'] : ''); // create chunks
							@unlink($filePath);
							$filePath = $csv->xml_path;												
						}
						else $this->errors->add('form-validation', $filePath->get_error_message());

					} else {
						
						$fileInfo = ('gz' == $item->feed_type or '' == $item->feed_type and preg_match('%\W(gz)$%i', trim($item->path))) ? pmxi_gzfile_get_contents($item->path) : pmxi_copy_url_file($item->path, true);
						if ( ! is_wp_error($fileInfo)){
							$filePath = $fileInfo['localPath'];														

							// detect CSV or XML 
							if ( $fileInfo['type'] == 'csv') { // it is CSV file																
								include_once(PMXI_Plugin::ROOT_DIR.'/libraries/XmlImportCsvParse.php');					
								$csv = new PMXI_CsvParser($filePath, true, '', ( ! empty($item->options['delimiter']) ) ? $item->options['delimiter'] : '', ( ! empty($item->options['encoding']) ) ? $item->options['encoding'] : ''); // create chunks
								@unlink($filePath);
								$filePath = $csv->xml_path;																			
							}
						}
						else $this->errors->add('form-validation', $fileInfo->get_error_message());
					}

				} elseif ( ! in_array($item->type, array('ftp')) ) { // if import type NOT URL

					if (preg_match('%\W(zip)$%i', trim(basename($item->path)))) {
						
						include_once(PMXI_Plugin::ROOT_DIR.'/libraries/pclzip.lib.php');

						$archive = new PclZip(trim($item->path));
					    if (($v_result_list = $archive->extract(PCLZIP_OPT_PATH, $uploads['path'], PCLZIP_OPT_REPLACE_NEWER)) == 0) {
					    	$this->errors->add('form-validation', 'Failed to open uploaded ZIP archive : '.$archive->errorInfo(true));			    	
					   	}
						else {
							
							$filePath = '';

							if (!empty($v_result_list)){
								foreach ($v_result_list as $unzipped_file) {									
									if ($unzipped_file['status'] == 'ok' and preg_match('%\W(xml|csv|txt|dat|psv)$%i', trim($unzipped_file['stored_filename']))) { $filePath = $unzipped_file['filename']; break; }	
								}
							}
					    	if($uploads['error']){
								 $this->errors->add('form-validation', __('Can not create upload folder. Permision denied', 'pmxi_plugin'));
							}

							if(empty($filePath)){						
								$zip = zip_open(trim($item->path));
								if (is_resource($zip)) {																		
									while ($zip_entry = zip_read($zip)) {
										$filePath = zip_entry_name($zip_entry);												
									    $fp = fopen($uploads['path']."/".$filePath, "w");
									    if (zip_entry_open($zip, $zip_entry, "r")) {
									      $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
									      fwrite($fp,"$buf");
									      zip_entry_close($zip_entry);
									      fclose($fp);
									    }
									    break;
									}
									zip_close($zip);							

								} else {
							        $this->errors->add('form-validation', __('Failed to open uploaded ZIP archive. Can\'t extract files.', 'pmxi_plugin'));
							    }						
							}															

							if (preg_match('%\W(csv|txt|dat|psv)$%i', trim($filePath))){ // If CSV file found in archieve						

								if($uploads['error']){
									 $this->errors->add('form-validation', __('Can not create upload folder. Permision denied', 'pmxi_plugin'));
								}																																			
								include_once(PMXI_Plugin::ROOT_DIR.'/libraries/XmlImportCsvParse.php');
								$csv = new PMXI_CsvParser($filePath, true, '', ( ! empty($item->options['delimiter']) ) ? $item->options['delimiter'] : '', ( ! empty($item->options['encoding']) ) ? $item->options['encoding'] : ''); // create chunks
								$filePath = $csv->xml_path;																
							}							
						}					

					} elseif ( preg_match('%\W(csv|txt|dat|psv)$%i', trim($item->path))) { // If CSV file uploaded										
						if($uploads['error']){
							 $this->errors->add('form-validation', __('Can not create upload folder. Permision denied', 'pmxi_plugin'));
						}											    																			
						include_once(PMXI_Plugin::ROOT_DIR.'/libraries/XmlImportCsvParse.php');					
						$csv = new PMXI_CsvParser($item->path, true, '', ( ! empty($item->options['delimiter']) ) ? $item->options['delimiter'] : '', ( ! empty($item->options['encoding']) ) ? $item->options['encoding'] : '');					
						$filePath = $csv->xml_path;						

					} elseif(preg_match('%\W(gz)$%i', trim($item->path))){ // If gz file uploaded
						$fileInfo = pmxi_gzfile_get_contents($item->path);
						if ( ! is_wp_error($fileInfo) ){
							$filePath = $fileInfo['localPath'];				
							// detect CSV or XML 
							if ( $fileInfo['type'] == 'csv') { // it is CSV file																
								include_once(PMXI_Plugin::ROOT_DIR.'/libraries/XmlImportCsvParse.php');					
								$csv = new PMXI_CsvParser($filePath, true, '', ( ! empty($item->options['delimiter']) ) ? $item->options['delimiter'] : '', ( ! empty($item->options['encoding']) ) ? $item->options['encoding'] : ''); // create chunks
								$filePath = $csv->xml_path;																			
							}
						}
						else $this->errors->add('form-validation', $fileInfo->get_error_message());

					} else { // If XML file uploaded					
						
						$filePath = $item->path;
						
					}

				}																						

				@set_time_limit(0);															
				$local_paths = !empty($local_paths) ? $local_paths : array($filePath);								

				foreach ($local_paths as $key => $path) {

					if (!empty($action_type) and $action_type == 'continue'){
						$chunks = $item->count;							
					}
					else{

						$file = new PMXI_Chunk($path, array('element' => $item->root_element, 'encoding' => $item->options['encoding']));					
				    						    
					    while ($xml = $file->read()) {					      						    					    					    	
					    	
					    	if (!empty($xml))
					      	{												      		
					      		PMXI_Import_Record::preprocessXml($xml);	
					      		$xml = "<?xml version=\"1.0\" encoding=\"". $item->options['encoding'] ."\"?>" . "\n" . $xml;					      		      						      							      					      	
						      					      		
						      	$dom = new DOMDocument('1.0', ( ! empty($item->options['encoding']) ) ? $item->options['encoding'] : 'UTF-8');															
								$old = libxml_use_internal_errors(true);
								$dom->loadXML($xml); // FIX: libxml xpath doesn't handle default namespace properly, so remove it upon XML load							
								libxml_use_internal_errors($old);
								$xpath = new DOMXPath($dom);
								if (($elements = @$xpath->query($item->xpath)) and !empty($elements) and !empty($elements->length)) $chunks += $elements->length;
								unset($dom, $xpath, $elements);										
						    }
						}	
						unset($file);
					}
														
					!$key and $filePath = $path;					
				}				

				if (empty($chunks)) 
					$this->errors->add('form-validation', __('No matching elements found for Root element and XPath expression specified', 'pmxi_plugin'));						
																		   							
			}							
			
			if ( $chunks ) { // xml is valid		
				
				if ( ! PMXI_Plugin::is_ajax() and empty(PMXI_Plugin::$session->data['pmxi_import']['chunk_number'])){								

					// compose data to look like result of wizard steps				
					PMXI_Plugin::$session['pmxi_import'] = array(						
						'filePath' => $filePath,
						'source' => array(
							'name' => $item->name,
							'type' => $item->type,						
							'path' => $item->path,
							'root_element' => $item->root_element,
						),
						'feed_type' => $item->feed_type,
						'update_previous' => $item->id,
						'parent_import_id' => $item->parent_import_id,
						'xpath' => $item->xpath,
						'template' => $item->template,
						'options' => $item->options,
						'encoding' => (!empty($item->options['encoding'])) ? $item->options['encoding'] : 'UTF-8',
						'is_csv' => (!empty($item->options['delimiter'])) ? $item->options['delimiter'] : PMXI_Plugin::$is_csv,
						'csv_path' => PMXI_Plugin::$csv_path,
						'scheduled' => $item->scheduled,														
						'chunk_number' => 1,						
						'log' => '',						
						'warnings' => 0,
						'errors' => 0,
						'start_time' => 0,
						'pointer' => 1,
						'count' => (isset($chunks)) ? $chunks : 0,
						'local_paths' => (!empty($local_paths)) ? $local_paths : array(), // ftp import local copies of remote files
						'action' => (!empty($action_type) and $action_type == 'continue') ? 'continue' : 'update',					
					);										
					
					pmxi_session_commit();
					
				}

				// deligate operation to other controller
				$controller = new PMXI_Admin_Import();
				$controller->data['update_previous'] = $item;
				$controller->process();
				return;
			}
		}				
		$this->render();
	}
	
	/**
	 * Delete an import
	 */
	public function delete() {
		$id = $this->input->get('id');
		$this->data['item'] = $item = new PMXI_Import_Record();
		if ( ! $id or $item->getById($id)->isEmpty()) {
			wp_redirect($this->baseUrl); die();
		}
		
		if ($this->input->post('is_confirmed')) {
			check_admin_referer('delete-import', '_wpnonce_delete-import');
			
			do_action('pmxi_before_import_delete', $item, $this->input->post('is_delete_posts'));

			$item->delete( ! $this->input->post('is_delete_posts'));
			wp_redirect(add_query_arg('pmxi_nt', urlencode(__('Import deleted', 'pmxi_plugin')), $this->baseUrl)); die();
		}
		
		$this->render();
	}
	
	/**
	 * Bulk actions
	 */
	public function bulk() {
		check_admin_referer('bulk-imports', '_wpnonce_bulk-imports');
		if ($this->input->post('doaction2')) {
			$this->data['action'] = $action = $this->input->post('bulk-action2');
		} else {
			$this->data['action'] = $action = $this->input->post('bulk-action');
		}		
		$this->data['ids'] = $ids = $this->input->post('items');
		$this->data['items'] = $items = new PMXI_Import_List();
		if (empty($action) or ! in_array($action, array('delete')) or empty($ids) or $items->getBy('id', $ids)->isEmpty()) {
			wp_redirect($this->baseUrl); die();
		}
		
		if ($this->input->post('is_confirmed')) {
			$is_delete_posts = $this->input->post('is_delete_posts');
			foreach($items->convertRecords() as $item) {
				$item->delete( ! $is_delete_posts);
			}
			
			wp_redirect(add_query_arg('pmxi_nt', urlencode(sprintf(__('<strong>%d</strong> %s deleted', 'pmxi_plugin'), $items->count(), _n('import', 'imports', $items->count(), 'pmxi_plugin'))), $this->baseUrl)); die();
		}
		
		$this->render();
	}

	/*
	 * Download import log file
	 *
	 */
	public function log(){

		$id = $this->input->get('id');
		
		$wp_uploads = wp_upload_dir();

		PMXI_download::csv($wp_uploads['basedir'] . '/wpallimport_logs/' .$id.'.html');

	}
}