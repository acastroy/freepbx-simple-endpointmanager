<?php

if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}
include('includes/webprov.php');

$prov = new webprov();

//Load Provisioner Library stuff
define('PROVISIONER_BASE', $prov->path.'/provisioner/');

$filename = basename($_SERVER["REQUEST_URI"]);
$web_path = 'http://'.$_SERVER["SERVER_NAME"].dirname($_SERVER["PHP_SELF"]).'/';

$strip = str_replace('spa', '', $filename);
if(preg_match('/[0-9A-Fa-f]{12}/i', $strip, $matches) && !(preg_match('/[0]{10}[0-9]{2}/i',$strip))) {
        require_once(PROVISIONER_BASE.'autoload.php');
	$mac = $matches[0];
        //Now search for this mac in the table :-)
        
        $sql = "SELECT * FROM simple_endpointman_mac_list WHERE mac = '". $mac."'";
        $res = $db->query($sql);
        if($res->numRows() > 0) {
            
                        //Returns Brand Name, Brand Directory, Model Name, Mac Address, Extension (FreePBX), Custom Configuration Template, Custom Configuration Data, Product Name, Product ID, Product Configuration Directory, Product Configuration Version, Product XML name,
            $sql = "SELECT * FROM simple_endpointman_mac_list WHERE mac = '". $mac . "'";

            $phone_info = $db->getRow($sql, array(), DB_FETCHMODE_ASSOC);


            $sql = "SELECT simple_endpointman_line_list.*, sip.data as secret, devices.*, simple_endpointman_line_list.description AS epm_description FROM simple_endpointman_line_list, sip, devices WHERE simple_endpointman_line_list.ext = devices.id AND simple_endpointman_line_list.ext = sip.id AND sip.keyword = 'secret' AND simple_endpointman_line_list.mac_id = ".$phone_info['id']." ORDER BY simple_endpointman_line_list.line ASC";
            $lines_info = $db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
            foreach($lines_info as $line) {
                $phone_info['line'][$line['line']] = $line;
                $phone_info['line'][$line['line']]['description'] = $line['epm_description'];
            }
            
            $brand = $phone_info['brand'];
            $family = $phone_info['product'];
            $model = $phone_info['model'];
            
            //Load Provisioner
            $class = "endpoint_" . $brand . "_" . $family . '_phone';
            $base_class = "endpoint_" . $brand. '_base';
            $master_class = "endpoint_base";

            if(!class_exists($master_class)) {
                ProvisionerConfig::endpointsAutoload($master_class);
            }
            if(!class_exists($base_class)) {
                ProvisionerConfig::endpointsAutoload($base_class);
            }
            if(!class_exists($class)) {
                ProvisionerConfig::endpointsAutoload($class);
            }

            if(!class_exists($class)) { die('Unable to load class: '. $class); }
            
            $endpoint = new $class();

            $endpoint->server_type = 'dynamic';		//Can be file or dynamic
            $endpoint->provisioning_type = 'http';

            //have to because of versions less than php5.3
            $endpoint->brand_name = $brand;
            $endpoint->family_line = $family;

            $endpoint->processor_info = "Web Provisioner 2.0";

            //Mac Address
            $endpoint->mac = $mac;

            //Phone Model (Please reference family_data.xml in the family directory for a list of recognized models)
            $endpoint->model = $model;
            
            	//Timezone
            if (!class_exists("DateTimeZone")) { require(PROVISIONER_BASE.'samples/tz.php'); }
            $endpoint->DateTimeZone = new DateTimeZone('America/Los_Angeles');

            //Server IP Address & Port
            $endpoint->server[1]['ip'] = '192.168.1.5';
            $endpoint->server[1]['port'] = 5060;

            //$endpoint->proxy[1]['ip'] = $data['data']['statics']['proxyserver'];
            //$endpoint->proxy[1]['port'] = 5060;

            $endpoint->provisioning_path = 'http://www.provisioner.net'.dirname($_SERVER['REQUEST_URI'])."/";

            //Loop through Lines!
            foreach($phone_info['line'] as $line) {
                $endpoint->lines[$line['line']] = array('ext' => $line['ext'], 'secret' => $line['secret'], 'displayname' => $line['description']);
            }
            
            $endpoint->options =  array();;

            

            $returned_data = $endpoint->generate_config();
                        
            ksort($returned_data);

            if(array_key_exists($filename, $returned_data)) {
                echo $returned_data[$filename];
            } else {
                header("HTTP/1.0 404 Not Found");
            }
        } else {
            header("HTTP/1.0 404 Not Found");
        }

} else {
    require_once (PROVISIONER_BASE.'endpoint/base.php');
    $data = Provisioner_Globals::dynamic_global_files($filename,$provisioner_path='/tmp/',$web_path);
    if($data !== FALSE) {
        echo $data;
    } else {
        header("HTTP/1.0 404 Not Found");
    }
}
