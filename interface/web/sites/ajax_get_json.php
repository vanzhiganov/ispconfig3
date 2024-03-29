<?php

/*
Copyright (c) 2005, Till Brehm, projektfarm Gmbh
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

require_once('../../lib/config.inc.php');
require_once('../../lib/app.inc.php');

//* Check permissions for module
$app->auth->check_module_permissions('sites');

$app->uses('getconf');

$server_id = $app->functions->intval($_GET["server_id"]);
$web_id = $app->functions->intval($_GET["web_id"]);
$php_type = $_GET["php_type"];
$type = $_GET["type"];

//if($_SESSION["s"]["user"]["typ"] == 'admin') {

	if($type == 'getservertype'){
		$json = '{"servertype":"';
		$server_type = 'apache';
		$web_config = $app->getconf->get_server_config($server_id, 'web');
		if(!empty($web_config['server_type'])) $server_type = $web_config['server_type'];
		$json .= $server_type;
		unset($webconfig);
		$json .= '"}';
	}
	
	if($type == 'getserverid'){
		$json = '{"serverid":"';
		$sql = "SELECT server_id FROM web_domain WHERE domain_id = $web_id";
		$server = $app->db->queryOneRecord($sql);
		$json .= $server['server_id'];
		unset($server);
		$json .= '"}';
	}
	
	if($type == 'getphpfastcgi'){
		$json = '{';
		
		$server_type = 'apache';
		$web_config = $app->getconf->get_server_config($server_id, 'web');
		if(!empty($web_config['server_type'])) $server_type = $web_config['server_type'];
		if($server_type == 'nginx' && $php_type == 'fast-cgi') $php_type = 'php-fpm';
		if($php_type == 'php-fpm'){
			$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = $server_id");
		}
		if($php_type == 'fast-cgi'){
			$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = $server_id");
		}
		$php_select = "";
		if(is_array($php_records) && !empty($php_records)) {
			foreach( $php_records as $php_record) {
				if($php_type == 'php-fpm'){
					$php_version = $php_record['name'].':'.$php_record['php_fpm_init_script'].':'.$php_record['php_fpm_ini_dir'].':'.$php_record['php_fpm_pool_dir'];
				} else {
					$php_version = $php_record['name'].':'.$php_record['php_fastcgi_binary'].':'.$php_record['php_fastcgi_ini_dir'];
				}
				$json .= '"'.$php_version.'": "'.$php_record['name'].'",';
			}
		}
		unset($php_records);
		if(substr($json,-1) == ',') $json = substr($json,0,-1);
		$json .= '}';
	}
	
	if($type == 'getphptype'){
		$json = '{"phptype":"';
		$sql = "SELECT php FROM web_domain WHERE domain_id = $web_id";
		$php = $app->db->queryOneRecord($sql);
		$json .= $php['php'];
		unset($php);
		$json .= '"}';
	}
	
	if($type == 'getredirecttype'){
		$json = '{"redirecttype":"';
		$sql = "SELECT redirect_type FROM web_domain WHERE domain_id = $web_id";
		$redirect = $app->db->queryOneRecord($sql);
		$json .= $redirect['redirect_type'];
		unset($redirect);
		$json .= '"}';
	}
	
	if($type == 'get_ipv4'){		
		$result = array();
		
		// ipv4
		//$result[] = _search('admin', 'server_ip', "AND ip_type = 'IPv4' AND (client_id = 0 OR client_id=".$app->functions->intval($_SESSION['s']['user']['client_id']).")");
		$result[] = $app->functions->suggest_ips('IPv4');

		$json = $app->functions->json_encode($result);
	}
	
	if($type == 'get_ipv6'){		
		$result = array();
		
		// ipv6
		//$result[] = _search('admin', 'server_ip', "AND ip_type = 'IPv6' AND (client_id = 0 OR client_id=".$app->functions->intval($_SESSION['s']['user']['client_id']).")");
		$result[] = $app->functions->suggest_ips('IPv6');
		
		$json = $app->functions->json_encode($result);
	}
    
    if($type == 'getdatabaseusers') {
        $json = '{}';
		
		$sql = "SELECT sys_groupid FROM web_domain WHERE domain_id = $web_id";
        $group = $app->db->queryOneRecord($sql);
        if($group) {
            $sql = "SELECT database_user_id, database_user FROM web_database_user WHERE sys_groupid = '" . $group['sys_groupid'] . "'";
            $records = $app->db->queryAllRecords($sql);
            
			$tmp_array = array();
            foreach($records as $record) {
				$tmp_array[$record['database_user_id']] = $record['database_user'];
            }
			$json = $app->functions->json_encode($tmp_array);
            unset($records, $group, $tmp_array);
        }
    }
	
	if($type == 'get_use_combobox'){
		$json = '{"usecombobox":"';
		$use_combobox = 'y';
		$server_config_array = $app->getconf->get_global_config();
		if($server_config_array['misc']['use_combobox'] != 'y') $use_combobox = 'n';
		$json .= $use_combobox;
		unset($server_config_array);
		$json .= '"}';
	}
	
	if($type == 'get_use_loadindicator'){
		$json = '{"useloadindicator":"';
		$use_loadindicator = 'y';
		$server_config_array = $app->getconf->get_global_config();
		if($server_config_array['misc']['use_loadindicator'] != 'y') $use_loadindicator = 'n';
		$json .= $use_loadindicator;
		unset($server_config_array);
		$json .= '"}';
	}

//}

header('Content-type: application/json');
echo $json;
?>