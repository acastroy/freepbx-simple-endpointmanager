<?php
$mac = isset($_REQUEST['mac']) ? $_REQUEST['mac'] : NULL;

if(isset($_REQUEST['save'])) {
    $options = array();
    $lines = array();

    foreach ($_REQUEST as $key => $value) {
        if ((preg_match('/(.*)\|(.*)\|(.*)/', $key, $matches)) OR (preg_match('/(.*)\|(.*)/', $key, $matches))) {
            switch ($matches[1]) {
                case 'loop':
                    if (preg_match('/(.*)_(.*)_(.*)/', $matches[2], $matches2)) {
                        $options['data'][$matches2[1]][$matches2[2]][$matches2[3]] = $value;
                    } else {
                        die('Invalid Loop');
                    }
                    break;
                case 'option':
                    $options['data'][$matches[2]] = $value;
                    break;
                case 'admin':
                    $options['admin'][$matches[2] . '|' . $matches[3]] = TRUE;
                    break;
                case 'lineloop':
                    if (preg_match('/^(.*)_(\d)_(.*)$/', $matches[2], $matches2)) {
                        $options['lines'][$matches2[2]][$matches2[3]] = $value;
                    } else {
                        die('invalid line option');
                    }
                    break;
                default:
                    die('DEFLECTOR SHEILDS UP');
            }
        }
    }
        
    if (isset($_POST['admin']) && ($_POST['admin'] == 1)) {
            $sql = "UPDATE simple_endpointman_mac_list SET global_custom_cfg_data = '" . addslashes(json_encode($options)) . "' WHERE mac = '" . $mac . "'";
            $prov->db->query($sql);
    } else {
            $sql = "UPDATE simple_endpointman_mac_list SET global_user_cfg_data = '" . addslashes(json_encode($options)) . "' WHERE mac = '" . $mac . "'";
            $prov->db->query($sql);    }
}

$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    //include_once('/etc/asterisk/freepbx.conf');
}

$admin = isset($_REQUEST['admin']) ? true : false;

if(isset($mac)) {
    $device_info = $prov->get_device_info($mac);
    if(empty($device_info['global_custom_cfg_data']) && !$admin) {
        die('Device has not been setup by an admin!');
    }
} else {
    die('Mac not set');
}

$global_settings = $prov->get_data($mac, 'settings', 'mac');

$has_sidecar1 = $global_settings['enable_sidecar1'] ?  NULL : 'a_unit1.xml';
$has_sidecar2 = $global_settings['enable_sidecar2'] ?  NULL : 'a_unit2.xml';

DEFINE('PROVISIONER_PATH', 'includes/provisioner/');
DEFINE('BRAND', 'cisco');
DEFINE('PRODUCT', 'spa5xx');


$only_show = array();


// Get user config data.
$user_data = $device_info['global_user_cfg_data'];
if(isset($mac)) {
    $saved_data = $device_info['global_custom_cfg_data'];
    if (!$admin) {
        foreach ($saved_data['admin'] as $key => $data) {
            $only_show[] = $key;
        }
    }
} else {
    $saved_data = array();
}

// What sort of phone do they have?
$model = $device_info['model'];

include('includes/generate_gui.class');

/*
// Lets do some sanity checking. Has this phone been modified?
if (isset($user_data['provisioned']) != true) {
	// It hasn't. Lets set it up with some defaults.
	$lines_default = array(
		"1" => 'self',
		"2" => array(
			"displaynameline" => "BLF - 332",
			"keytype" => "blf",
			"blfext" => "332",
		)
	);
	$prov->set_data($ext, 'lineops', array("2" => $lines_default), 'user', 'line');
	$prov->set_data($ext, 'provisioned', true, 'user', 'line');
	$prov->set_data($ext, 'has-sidecar1', false, 'user', 'line');
	$prov->set_data($ext, 'has-sidecar2', false, 'user', 'line');

	// And now, reload our data.
	$user_data = $prov->get_data($ext, 'user', 'line');
}
 *
 */

$gui = new generate_gui();
$dont_load = array($has_sidecar1,$has_sidecar2);

$output = $gui->create_template_array(BRAND, PRODUCT, $model,$dont_load);

$dont_show = array(
    'loop|lineops_1_displaynameline',
    'loop|lineops_1_keytype',
    'loop|lineops_1_blfext',
    'option|upgrade_path',
    'option|page_code',
    'option|webserver_port',
    'option|administrator_password',
    'option|user_password',
    'option|enable_upgrade',
    'option|text_logo',
    'option|dial_plan',
    'option|background_type',
    'option|logo_type',
    'option|picture_url',
    'option|enable_webserver',
    'option|enable_webserver_admin',
    'option|station_name',
    'option|date_format',
    'option|ring1',
    'option|ring2',
    'option|ring3',
    'option|ring4',
    'option|ring5',
    'option|ring6',
    'option|ring7',
    'option|ring8',
    'option|ring9',
    'option|ring10'
);

echo '<form method="post" id="edit">';
echo '<table>';

foreach ($output['data'] as $sections => $data) {
    $show_sections = TRUE;
    foreach ($data as $subsections => $sub_data) {
        $show_subsections = TRUE;
        foreach ($sub_data as $variables => $sub_variables) {
            foreach ($sub_variables as $variable_key => $html_els) {
                if ($variable_key == '0') {
                    $var = $variables;
                } else {
                    $var = $variables . '_' . $variable_key;
                }
                if ($html_els['default_value'] != 'corn') {

                    $user_value = NULL;
                    if (isset($saved_data)) {
                        if($admin) {
                            $user_data = $saved_data;
                        } else {
                            $user_data = isset($user_data) ? $user_data : $saved_data;
                        }
                        preg_match('/(.*)\|(.*)/', $var, $matches);
                        switch ($matches[1]) {
                            case 'loop':
                                if (preg_match('/(.*)_(.*)_(.*)/', $matches[2], $matches2)) {
                                    $user_value = isset($user_data['data'][$matches2[1]][$matches2[2]][$matches2[3]]) ? $user_data['data'][$matches2[1]][$matches2[2]][$matches2[3]] : $saved_data['data'][$matches2[1]][$matches2[2]][$matches2[3]];
                                }
                                break;
                            case 'option':
                                $user_value = isset($user_data['data'][$matches[2]]) ? $user_data['data'][$matches[2]] : $saved_data['data'][$matches2[1]][$matches2[2]][$matches2[3]];
                                break;
                            case 'lineloop':
                                die('COME BACK TO THIS');
                                break;
                            default:
                                die('AHH');
                                break;
                        }
                    }

                    //Left over breaks that can be safely ignored (basically corn)
                    $output = $gui->generate_html($html_els, $var, $user_value, $only_show, $dont_show);
                    if ($output !== FALSE) {
                        echo $show_sections ? "<tr><td colspan='2'><h1>" . $sections . "</h1></td></tr>" : '';
                        echo $show_subsections ? "<tr><td colspan='2'><h2>" . $subsections . "</h2></td></tr>" : '';

                        if(!empty($saved_data['admin'])) {
                            $checked = isset($saved_data['admin'][$var]) ? 'checked' : '';
                        }
                        $admin_out = $admin ? '<input type="checkbox" name="admin|' . $var . '" value="Bike" '.$checked.'/> Allow Users to edit this' : '';

                        echo '<tr><td>' . $output . '</td><td>' . $admin_out . '</td></tr>';

                        $show_sections = FALSE;
                        $show_subsections = FALSE;
                    }
                } else {
                    
                }
            }
        }
    }
}
echo '</table>';

//ghetto hack, will fix later
$admin_line = $admin ? '<input type="hidden" name="admin" value="' . $admin . '"/>' : '';
echo $admin_line;
echo '<input type="hidden" name="brand" value="' . BRAND . '"/>';
echo '<input type="hidden" name="product" value="' . PRODUCT . '"/>';
echo '<input type="hidden" name="model" value="' .$model . '"/>';
echo '<input type="hidden" name="mac" value="' .$mac . '"/>';
echo '<input type="hidden" name="save" value="true"/>';
echo '<input type="submit" value="Save" />';
echo '</form>';
