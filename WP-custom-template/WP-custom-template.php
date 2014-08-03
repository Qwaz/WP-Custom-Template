<?php
	/*
	Plugin Name: WP Custom Template
	Description: Quickly create your own template in settings page.
	Version: 0.9
	Author: Qwaz
	*/

	function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . "custom_templates";
	}

	function jal_install () {
		global $wpdb;
		$table_name = get_table_name();

		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name){
			$sql = "CREATE TABLE $table_name (
			  id int NOT NULL AUTO_INCREMENT,
			  name VARCHAR(20) DEFAULT '' NOT NULL,
			  content text NOT NULL,
			  description text NOT NULL,
			  UNIQUE KEY id (id)
			);";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );

			$arr = array(
				'name'=>'default',
				'content'=>'template not found :(',
				'description'=>'default template message will be shown when no correspond template exists');

			$wpdb->insert($table_name, $arr);
		}
	}

	function jal_uninstall () {
		global $wpdb;
		$table_name = get_table_name();

		$wpdb->query("DROP TABLE $table_name;");
	}

	register_activation_hook( __FILE__, 'jal_install');
	register_uninstall_hook( __FILE__, 'jal_uninstall');

	function template_func ( $atts ) {
		$template = 'default';

		foreach ($atts as $key => $value)
			$template = $value;

		$atts = shortcode_atts( array(
			'name' => 'default'
		), $atts);

		global $wpdb;
		$table_name = get_table_name();

		$row_check = $wpdb->get_row("SELECT * FROM $table_name WHERE name = '$template'");
		if($row_check == NULL){
			$row_check = $wpdb->get_row("SELECT * FROM $table_name WHERE name = 'default'");
		}

		return $row_check->content;
	}
	add_shortcode( 'template','template_func' );

	if(is_admin())
		add_action( 'admin_menu', 'my_plugin_menu');

	function my_plugin_menu() {
		add_options_page( 'Manage Templates', 'Manage Templates', 'manage_options', 'manage-custom-templates', 'manage_templates');
	}

	add_action('wp_ajax_update_template_data', 'update_template_data');

	function update_template_data() {
		global $wpdb;
		$table_name = get_table_name();

		$original = $_POST['original'];
		$name = $_POST['name'];
		$content = $_POST['content'];
		$description = $_POST['description'];
		$deleting = $_POST['deleting'];

		$data = array('name'=>$name, 'content'=>$content, 'description'=>$description);
		$where = array('name'=>$original);

		$obj = $wpdb->get_row("SELECT * FROM $table_name where name = '$original'");

		if($name == ''){
			echo "Please enter template name.";
		} else if($obj == NULL){
			if($deleting == "true"){
				//error
				echo "Template named $original does not exist.";
			} else {
				//insert
				if($wpdb->get_row("SELECT * FROM $table_name where name = '$name'")){
					echo "Template named $name already exists.";
				} else {
					$wpdb->insert($table_name, $data);
					echo "Template named $name is created.";
				}
			}
		} else {
			if($deleting == "true"){
				//delete
				$wpdb->delete($table_name, $where);
				echo "Template named $original is deleted.";
			} else {
				//update
				$wpdb->update($table_name, $data, $where);
				echo "Template named $original is updated.";
			}
		}

		die();
	}

	add_action('wp_ajax_get_template_data', 'get_template_data');

	function get_template_data() {
		global $wpdb;
		$table_name = get_table_name();

		$template_name = $_POST['name'];
		$obj = $wpdb->get_row("SELECT * FROM $table_name where name = '$template_name'");

		if($obj == NULL){
			$obj = (object) array('name'=>'', 'content'=>'', 'description'=>'');
		}

		?>
{
	"name_text":"<?=$obj->name?>",
	"content_text":"<?=$obj->content?>",
	"description_text":"<?=$obj->description?>"
}
		<?php

		die();
	}

	add_action('wp_ajax_get_template_list', 'get_template_list');

	function get_template_list() {
		global $wpdb;
		$table_name = get_table_name();

		$list = array();

		$list[] = array('text'=>'Create New Template', 'value'=>'');
		$list[] = array('text'=>'Default', 'value'=>'default');

		$rows = $wpdb->get_results("SELECT * FROM $table_name where name != 'default'");
		foreach ($rows as $obj){
			$list[] = array('text'=>$obj->name, 'value'=>$obj->name);
		}

		foreach ($list as $arr){
			echo "<option value='".$arr['value']."'>".$arr['text']."</option>";
		}

		die();
	}

	function manage_templates() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		?>

		<div class="wrap">
			<h2>WP Custom Template Settings</h2>

			<h4>Select Template</h4>
			<select id="template_original" onchange="updateForm()">
			</select>
			
			<h4>name</h4>
			<input id="template_name" type="text">

			<h4>Content</h4>
			<textarea id="template_content" rows="5" cols="40"></textarea>

			<h4>Description</h4>
			<textarea id="template_description" rows="5" cols="40"></textarea>

			<p>w
				<button id="template_submit" class="button button-primary" onclick="submitAjax(false)"><?=__('Save Changes')?></button>
				<button id="template_delete" class="button button-primary" onclick="submitAjax(true)"><?=__('Delete')?></button>
			</p>

			<span id="template_result"></span>

			<script type="text/javascript">
				last_template = '';

				jQuery(document).ready(function(){
					loadTemplateList();
				});

				function loadTemplateList(){
					waitForm();

					jQuery.post(ajaxurl, {
						action:'get_template_list'
					},
					function(data, status){
						if(status == "success"){
							jQuery("#template_original").html(data);
							jQuery("#template_original").val(last_template);

							updateForm();
						}
					})
				}

				function waitForm(){
					jQuery("#template_name").prop('disabled', true);
					jQuery("#template_content").prop('disabled', true);
					jQuery("#template_description").prop('disabled', true);
					jQuery("#template_submit").prop('disabled', true);
					jQuery("#template_delete").prop('disabled', true);
				}

				function updateForm(){
					waitForm();

					jQuery.post(ajaxurl, {
						action:'get_template_data',
						name:jQuery("#template_original").val()
					},
					function(data, status){
						if(status == "success"){
							data = JSON.parse(data);

							jQuery("#template_name").val(data.name_text);
							jQuery("#template_content").val(data.content_text);
							jQuery("#template_description").val(data.description_text);

							if(data.name_text != 'default')
								jQuery("#template_name").prop('disabled', false);

							if(data.name_text == '' || data.name_text == 'default'){
								jQuery("#template_delete").css("display", "none");
							} else {
								jQuery("#template_delete").css("display", "inline");
							}

							jQuery("#template_content").prop('disabled', false);
							jQuery("#template_description").prop('disabled', false);
							jQuery("#template_submit").prop('disabled', false);
							jQuery("#template_delete").prop('disabled', false);
						}
					});
				}

				function submitAjax(deleting) {
					waitForm();

					last_template = deleting ? '' : jQuery("#template_name").val();

					original_text = jQuery("#template_original").val();
					name_text = jQuery("#template_name").val();

					jQuery.post(ajaxurl, {
						action:'update_template_data',
						original:original_text,
						name:name_text,
						content:jQuery("#template_content").val(),
						description:jQuery("#template_description").val(),
						deleting:deleting
					}, function(data, status){
						jQuery("#template_result").text(data);

						if(status == "success"){
							if(!deleting && original_text == name_text) updateForm();
							else loadTemplateList();
						}
					});
				}
			</script>
		<?php
	}

?>