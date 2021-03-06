<?php
  /* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 2.0.0-18                                               |
  | http://www.elastix.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
  +----------------------------------------------------------------------+
  | Cdla. Nueva Kennedy Calle E 222 y 9na. Este                          |
  | Telfs. 2283-268, 2294-440, 2284-356                                  |
  | Guayaquil - Ecuador                                                  |
  | http://www.palosanto.com                                             |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | The Original Code is: Elastix Open Source.                           |
  | The Initial Developer of the Original Code is PaloSanto Solutions    |
  +----------------------------------------------------------------------+
  $Id: index.php,v 1.3 2007/09/05 00:26:21 gcarrillo Exp $
  $Id: index.php,v 1.3 2008/04/14 09:22:21 afigueroa Exp $
  $Id: index.php,v 2.0 2010/02/03 09:00:00 onavarre Exp $
  $Id: index.php,v 2.1 2010-03-22 05:03:48 Eduardo Cueva ecueva@palosanto.com Exp $ */
//include elastix framework

// exten => s,n,Set(CDR(userfield)=audio:${CALLFILENAME}.${MIXMON_FORMAT})   extensions_additional
include_once "libs/paloSantoGrid.class.php";
include_once "libs/paloSantoForm.class.php";

function _moduleContent(&$smarty, $module_name)
{
    //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "modules/$module_name/libs/paloSantoMonitoring.class.php";
    include_once "libs/paloSantoACL.class.php";
    require_once "modules/$module_name/libs/JSON.php";
    require_once "modules/agent_console/getinfo.php";

    //include file language agree to elastix configuration
    //if file language not exists, then include language by default (en)
    $lang=get_language();
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);

    // Include language file for EN, then for local, and merge the two.
    include_once("modules/$module_name/lang/en.lang");
    $lang_file="modules/$module_name/lang/$lang.lang";
    if (file_exists("$base_dir/$lang_file")) {
        $arrLanEN = $arrLangModule;
        include_once($lang_file);
        $arrLangModule = array_merge($arrLanEN, $arrLangModule);
    }

    //global variables
    global $arrConf;
    global $arrConfModule;
    global $arrLang;
    global $arrLangModule;
    $arrConf = array_merge($arrConf,$arrConfModule);
    $arrLang = array_merge($arrLang,$arrLangModule);

    //folder path for custom templates
    $templates_dir=(isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    //conexion resource
    $arrConf['dsn_conn_database'] = generarDSNSistema('asteriskuser', 'asteriskcdrdb');
    $pDB = new paloDB($arrConf['dsn_conn_database']);
    $pDBACL = new paloDB($arrConf['elastix_dsn']['acl']);
    $pACL = new paloACL($pDBACL);
    $user = isset($_SESSION['elastix_user'])?$_SESSION['elastix_user']:"";
    //$extension = $pACL->getUserExtension($user);
    $extension = '6868';
    //$esAdministrador = $pACL->isUserAdministratorGroup($user);
    $esAdministrador = true;
    if($extension=="" || is_null($extension)){
        if($esAdministrador)
            $smarty->assign("mb_message", "<b>"._tr("no_extension")."</b>");
        else{
            $smarty->assign("mb_message", "<b>"._tr("contact_admin")."</b>");
            return "";
        }
    }

    //actions
    $action = getAction();
    $content = "";

    switch($action){
        case 'delete':
            //$content = deleteRecord($smarty, $module_name, $local_templates_dir, $pDB, $pACL, $arrConf, $user, $extension, $esAdministrador);
            break;
        case 'download':
            $content = downloadFile($smarty, $module_name, $local_templates_dir, $pDB, $pACL, $arrConf, $user, $extension, $esAdministrador);
            break;
        case "display_record":
            $content = display_record($smarty, $module_name, $local_templates_dir, $pDB, $pACL, $arrConf, $user, $extension, $esAdministrador);
            break;
        case "viewNote":
            $content = viewNote();
            break;
        case "viewDelivery":
            $content = viewDelivery();
            break;
        default:
            $content = reportMonitoring($smarty, $module_name, $local_templates_dir, $pDB, $pACL, $arrConf, $user, $extension, $esAdministrador);
            break;
    }
    return $content;
}

function viewNote()
{
    $sNoteId = trim(getParameter('view_note_id'));
    if ($sNoteId == '' || is_null($sNoteId)) {
        $response = 'Không có note id!';
        $json = new Services_JSON();
        Header('Content-Type: application/json');
        return $json->encode($response);
    }

    global $arrConf;
    $oCustomer = new getInfoMainConsole();
    $oCustomer->callcenter_db_connect($arrConf['cadena_dsn']);

    $result = $oCustomer->getNote($sNoteId);
    $oCustomer->callcenter_db_disconnect();

    // return json
    if (!$result)
        $response = 'Lỗi: ' . $oCustomer->errMsg;
    else
        $response = $result['note'];
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($response);
}

function channel_lookup($pDB_asterisk,$channel)
{
    if (trim($channel) == '')
        return '';
    $number = substr($channel,strpos($channel,'/')+1,strpos($channel,'-')-strpos($channel,'/')-1);
    $sql = "Select name from call_center.agent where number like '%$number%'";
    $r = $pDB_asterisk->getFirstRowQuery($sql, false);
	
    if (count($r) > 0)
        return $r[0].'-'.$number;
    else {
        $trunk = array(
            'GXWT1' =>  '38251123',
            'GXWT2' =>  '38273273',
            'GXWT3' =>  '38273899',
            'GXWT4' =>  '38248311',
            'GXWT5' =>  '38251379',
            'GXWT6' =>  '38251179',            
            'GXW2T1' =>  '38248325',
            'GXW2T2' =>  '38258234',
            'GXW2T3' =>  '38274291',
            'GXW2T4' =>  '38258220',
            'GXW2T5' =>  '38248329',
            'GXW2T6' =>  '38274251',
            'GXW2T7' =>  '38248940',            
			'GXW3T1' =>  '38273880',
            'GXW3T2' =>  '38273878',
            'GXW3T3' =>  '38274120',
            'GXW3T4' =>  '38248990',
            'GXW3T5' =>  '38248924',
            'GXW3T6' =>  '38248328',
            'GXW3T7' =>  '38245988',
            'GXW3T8' =>  '38248889',
			'GXW4T1' =>  '38248668',
            'GXW4T2' =>  '38248326',
            'GXW4T3' =>  '38273879',
            'GXW4T4' =>  '38273877',
            'GXW4T5' =>  '38248889',
            'GXW4T6' =>  '38248624',
            'GXW4T7' =>  '38248586',
            'GXW4T8' =>  '38273898',
			'GXW5T1' =>  '38221109',
            'GXW5T2' =>  '38273882',
            'GXW5T3' =>  '38221089',
            'GXW5T4' =>  '38221090',
            'GXW5T5' =>  '38221076',
            'GXW5T6' =>  '38273884',
            'GXW5T7' =>  '38220717',            
			'GXW6T1' =>  '38242362',
            'GXW6T2' =>  '38258208',
            'GXW6T3' =>  '38237905',
            'GXW6T4' =>  '38224791',
            'GXW6T5' =>  '38256256',
            'GXW6T6' =>  '38237913',            
        );
        foreach ($trunk as $key=>$value){
            if (strpos($channel,$key)>0)
                return $key.'-'.$value;
        }
    }
    return $channel;
}

function viewDelivery()
{
    $response = array(
        'action'    =>  'viewDelivery',
        'message'   =>  '(no message)',
    );

    $ticket_id = getParameter('view_delivery_id');

    global $arrConf;
    $oMainConsole = new getInfoMainConsole();
    $oMainConsole->callcenter_db_connect($arrConf['cadena_dsn']);
    $delivery  = $oMainConsole->getDeliveryById($ticket_id);
    $oMainConsole->callcenter_db_disconnect();
    if(!$result) {
        $response['action'] = 'error';
        $response['message'] = 'Lỗi: ' . $oMainConsole->errMsg;
    }
    //return json
    $response['message'] = $delivery;
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($response);
}

function reportMonitoring($smarty, $module_name, $local_templates_dir, &$pDB, $pACL, $arrConf, $user, $extension, $esAdministrador)
{
    $pMonitoring = new paloSantoMonitoring($pDB);
    //var_dump($arrConfg);
    $pMonitoring->setConfig($arrConf);
    $filter_field = getParameter("filter_field");

    switch($filter_field){
        case "dst":
            $filter_field = "dst";
            $nameFilterField = _tr("Destination");
            break;
        case "userfield":
            $filter_field = "userfield";
            $nameFilterField = _tr("Type");
            break;
        default:
            $filter_field = "src";
            $nameFilterField = _tr("Source");
            break;
    }
    if($filter_field == "userfield"){
        $filter_value     = getParameter("filter_value_userfield");
        $filter           = "";
        $filter_userfield = $filter_value;
    }
    else{
        $filter_value     = getParameter("filter_value");
        $filter           = $filter_value;
        $filter_userfield = "";
    }
    switch($filter_value){
        case "outgoing":
              $smarty->assign("SELECTED_2", "Selected");
              $nameFilterUserfield = _tr("Outgoing");
              break;
        case "queue":
              $smarty->assign("SELECTED_3", "Selected");
              $nameFilterUserfield = _tr("Queue");
              break;
        case "group":
              $smarty->assign("SELECTED_4", "Selected");
              $nameFilterUserfield = _tr("Group");
              break;
        default:
              $smarty->assign("SELECTED_1", "Selected");
              $nameFilterUserfield = _tr("Incoming");
              break;
    }
    $date_ini = getParameter("date_start");
    $date_end = getParameter("date_end");

    $path_record = $arrConf['records_dir'];

    $_POST['date_start'] = isset($date_ini)?$date_ini:date("d M Y");
    $_POST['date_end']   = isset($date_end)?$date_end:date("d M Y");

    if($date_ini===""){
        $_POST['date_start'] = " ";
    }
    if($date_end==="")
        $_POST['date_end'] = " ";

    if (!empty($pACL->errMsg)) {
        echo "ERROR DE ACL: $pACL->errMsg <br>";
    }

    $date_initial = date('Y-m-d',strtotime($_POST['date_start']))." 00:00:00";
    $date_final   = date('Y-m-d',strtotime($_POST['date_end']))." 23:59:59";

    $_DATA = $_POST;
    //begin grid parameters
    $oGrid  = new paloSantoGrid($smarty);
    $oGrid->setTitle(_tr("Monitoring"));
    $oGrid->setIcon("modules/$module_name/images/pbx_monitoring.png");
    $oGrid->pagingShow(true); // show paging section.

    $oGrid->enableExport();   // enable export.
    $oGrid->setNameFile_Export(_tr("Monitoring"));

    if($esAdministrador)
        $totalMonitoring = $pMonitoring->getNumMonitoring($filter_field, $filter_value, null, $date_initial, $date_final);
    elseif(!($extension=="" || is_null($extension)))
        $totalMonitoring = $pMonitoring->getNumMonitoring($filter_field, $filter_value, $extension, $date_initial, $date_final);
    else
        $totalMonitoring = 0;
    $url = array('menu' => $module_name);

    $paramFilter = array(
       'filter_field'           => $filter_field,
       'filter_value'           => $filter,
       'filter_value_userfield' => $filter_userfield,
       'date_start'             => $_POST['date_start'],
       'date_end'               => $_POST['date_end']
    );
    $url = array_merge($url, $paramFilter);
    $oGrid->setURL($url);

    $arrData = null;
    if($oGrid->isExportAction()){
        $limit = $totalMonitoring;
        $offset = 0;

        $arrColumns = array(_tr("Date"), _tr("Time"), _tr("Source"), _tr("Destination"),_tr("Duration"),_tr("Type"),_tr("File"));
        $oGrid->setColumns($arrColumns);

        if($esAdministrador)
            $arrResult =$pMonitoring->getMonitoring($limit, $offset, $filter_field, $filter_value, null, $date_initial, $date_final);
        elseif(!($extension=="" || is_null($extension)))
            $arrResult =$pMonitoring->getMonitoring($limit, $offset, $filter_field, $filter_value, $extension, $date_initial, $date_final);
        else
            $arrResult = array();

        if(is_array($arrResult) && $totalMonitoring>0){
            foreach($arrResult as $key => $value){
                $arrTmp[0] = date('d M Y',strtotime($value['calldate']));
                $arrTmp[1] = date('H:i:s',strtotime($value['calldate']));
                $arrTmp[2] = $value['src'];
                $arrTmp[3] = $value['dst'];
				$arrTmp[4] = $value['dstchannel'];
                $arrTmp[5] = SecToHHMMSS($value['duration']);
                $file = $value['uniqueid'];
                    $namefile = basename($value['userfield']);
                    $namefile = str_replace("audio:","",$namefile);
                    if ($namefile == 'deleted') {
                        $arrTmp[6] = _tr('Deleted');
                    } else switch($namefile[0]){
                         case "O":
                              $arrTmp[6] = _tr("Outgoing");
                         break;
                         case "g":
                              $arrTmp[6] = _tr("Group");
                         break;
                         case "q":
                              $arrTmp[6] = _tr("Queue");
                         break;
                         default :
                              $arrTmp[6] = _tr("Incoming");
                         break;
                    }
                $arrTmp[7] = $namefile;
                $arrTmp[8] = $value['note'];
                $arrTmp[9] = $value['delivery_id'];
                $arrData[] = $arrTmp;
            }
        }
    }
    else{
        $limit  = 20;
        $total  = $totalMonitoring;
        $oGrid->setLimit($limit);
        $oGrid->setTotal($total);
        $offset = $oGrid->calculateOffset();

        if($esAdministrador){
        $oGrid->deleteList(_tr("message_alert"),'submit_eliminar',_tr("Delete"));
            $buttonDelete = "";
        }
        else
            $buttonDelete = "";

        $arrColumns = array($buttonDelete, "Ngày", "Giờ", "Gọi từ", "Gọi đến","Nhân viên/PSTN","Thời gian gọi",
            "Loại", "Ghi âm", "Nội dung", "Giao vé");
        $oGrid->setColumns($arrColumns);

        if($esAdministrador)
            $arrResult =$pMonitoring->getMonitoring($limit, $offset, $filter_field, $filter_value, null, $date_initial, $date_final);
        elseif(!($extension=="" || is_null($extension)))
            $arrResult =$pMonitoring->getMonitoring($limit, $offset, $filter_field, $filter_value, $extension, $date_initial, $date_final);
        else
            $arrResult = array();


        if(is_array($arrResult) && $total>0){
            $src = "";
            $dst = "";
            foreach($arrResult as $key => $value){
                if($esAdministrador)
                    $arrTmp[0] = "<input type='checkbox' name='id_".$value['uniqueid']."' />";
                else
                    $arrTmp[0] = "";
                $arrTmp[1] = date('d M Y',strtotime($value['calldate']));
                $arrTmp[2] = date('H:i:s',strtotime($value['calldate']));
                if(!isset($value['src']) || $value['src']=="")
                    $src = "<font color='gray'>"._tr("unknown")."</font>";
                else
                    $src = $value['src'];
                if(!isset($value['dst']) || $value['dst']=="")
                    $dst = "<font color='gray'>"._tr("unknown")."</font>";
                else
                    $dst = $value['dst'];
                $arrTmp[3] = $src;
                $arrTmp[4] = $dst;
				$arrTmp[5] = channel_lookup($pDB,$value['dstchannel']);
                $arrTmp[6] = "<label title='".$value['duration']." seconds' style='color:green'>".SecToHHMMSS( $value['duration'] )."</label>";

                //$file = base64_encode($value['userfield']);
                $file = $value['uniqueid'];
                $namefile = basename($value['userfield']);
                $namefile = str_replace("audio:","",$namefile);
                if ($namefile == 'deleted') {
                    $arrTmp[7] = _tr('Deleted');
                } else switch($namefile[0]){
                      case "O":
                          $arrTmp[7] = _tr("Outgoing");
                      break;
                      case "g":
                          $arrTmp[7] = _tr("Group");
                      break;
                      case "q":
                          $arrTmp[7] = _tr("Queue");
                      break;
                      default :
                          $arrTmp[7] = _tr("Incoming");
                      break;
                }
                if ($namefile != 'deleted') {
                    $recordingLink = "<a  href=\"javascript:popUp('index.php?menu=$module_name&action=display_record&id=$file&namefile=$namefile&rawmode=yes',350,100);\">"._tr("Listen")."</a>&nbsp;";

                    $recordingLink .= "<a href='?menu=$module_name&action=download&id=$file&namefile=$namefile&rawmode=yes' >"._tr("Download")."</a>";
                } else {
                    $recordingLink = '';
                }

                $arrTmp[8] = $recordingLink;
                $arrTmp[9] = (is_null($value['note_id'])?'-':'<a href="javascript:void(0)" onclick="view_note(\'' . $value['note_id'] . '\')">Xem</a>');
                $arrTmp[10] = (is_null($value['delivery_id'])?'-':'<a href="javascript:void(0)" onclick="view_delivery(\'' . $value['delivery_id'] . '\')">Xem</a>');
                $arrData[] = $arrTmp;
            }
        }
    }
    $oGrid->setData($arrData);

    //begin section filter
    $arrFormFilterMonitoring = createFieldFilter();
    $oFilterForm = new paloForm($smarty, $arrFormFilterMonitoring);

    $smarty->assign("INCOMING", _tr("Incoming"));
    $smarty->assign("OUTGOING", _tr("Outgoing"));
    $smarty->assign("QUEUE", _tr("Queue"));
    $smarty->assign("GROUP", _tr("Group"));
    $smarty->assign("SHOW", _tr("Show"));
    $_POST["filter_field"]           = $filter_field;
    $_POST["filter_value"]           = $filter;
    $_POST["filter_value_userfield"] = $filter_userfield;

    $oGrid->addFilterControl(_tr("Filter applied ")._tr("Start Date")." = ".$paramFilter['date_start'].", "._tr("End Date")." = ".$paramFilter['date_end'], $paramFilter,  array('date_start' => date("d M Y"),'date_end' => date("d M Y")),true);

    if($filter_field == "userfield"){
        $oGrid->addFilterControl(_tr("Filter applied ")." $nameFilterField = $nameFilterUserfield", $_POST, array('filter_field' => "src",'filter_value_userfield' => "incoming"));
    }
    else{
        $oGrid->addFilterControl(_tr("Filter applied ")." $nameFilterField = $filter", $_POST, array('filter_field' => "src","filter_value" => ""));
    }

    $htmlFilter = $oFilterForm->fetchForm("$local_templates_dir/filter.tpl","",$_POST);
    //end section filter
    $oGrid->showFilter(trim($htmlFilter));
    $content = $oGrid->fetchGrid();

    //end grid parameters

    return $content;
}

function downloadFile($smarty, $module_name, $local_templates_dir, &$pDB, $pACL,
    $arrConf, $user, $extension, $esAdministrador)
{
    $record = getParameter("id");
    $namefile = getParameter('namefile');
    $pMonitoring = new paloSantoMonitoring($pDB);
    if(!$esAdministrador){
        if(!$pMonitoring->recordBelongsToUser($record, $extension)){
            $smarty->assign("mb_title", _tr("ERROR"));
            $smarty->assign("mb_message", _tr("You are not authorized to download this file"));
            return reportMonitoring($smarty, $module_name, $local_templates_dir, $pDB, $pACL, $arrConf, $user, $extension, $esAdministrador);
        }
    }
    $path_record = $arrConf['records_dir'];

    if (is_null($record) || !preg_match('/^[[:digit:]]+\.[[:digit:]]+$/', $record)) {
        // Missing or invalid uniqueid
        Header('HTTP/1.1 404 Not Found');
        die("<b>404 "._tr("no_file")." </b>");
    }

    // Check record is valid and points to an actual file
    $filebyUid = $pMonitoring->getAudioByUniqueId($record, $namefile);
    if (is_null($filebyUid) || count($filebyUid) <= 0) {
        // Uniqueid does not point to a record with specified file
        Header('HTTP/1.1 404 Not Found');
        die("<b>404 "._tr("no_file")." </b>");
    }
    $file = basename(str_replace('audio:', '', $filebyUid['userfield']));
    $path = $path_record.$file;
    if ($file == 'deleted') {
        // Specified file has been deleted
        Header('HTTP/1.1 404 Not Found');
        die("<b>404 "._tr("no_file")." </b>");
    }
    if (!file_exists($path)) {
    	// Queue recordings might lack an extension
        $arrData = glob("$path*");
        if (count($arrData) > 0) {
        	$path = $arrData[0];
            $file = basename($path);
        }
    }
    if (!file_exists($path) || !is_file($path)) {
        // Failed to find specified file
        Header('HTTP/1.1 404 Not Found');
        die("<b>404 "._tr("no_file")." </b>");
    }
    
    // Set Content-Type according to file extension
    $contentTypes = array(
        'wav'   =>  'audio/x-wav',
        'gsm'   =>  'audio/x-gsm',
        'mp3'   =>  'audio/mpeg',
    );
    $extension = substr(strtolower($file), -3);
    if (!isset($contentTypes[$extension])) {
        // Unrecognized file extension
    	Header('HTTP/1.1 404 Not Found');
        die("<b>404 "._tr("no_file")." </b>");
    }
    
    // Actually open and transmit the file
    $fp = fopen($path, 'rb');
    if (!$fp) {
        Header('HTTP/1.1 404 Not Found');
        die("<b>404 "._tr("no_file")." </b>");
    }
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: public");
    header("Content-Description: wav file");
    header("Content-Type: " . $contentTypes[$extension]);
    header("Content-Disposition: attachment; filename=" . $file);
    header("Content-Transfer-Encoding: binary");
    header("Content-length: " . filesize($path));
    fpassthru($fp);
    fclose($fp);
}

function record_format(&$pDB, $arrConf){
    $record = getParameter("id");
    $pMonitoring = new paloSantoMonitoring($pDB);

    $path_record = $arrConf['records_dir'];
    if (isset($record) && preg_match("/^[[:digit:]]+\.[[:digit:]]+$/",$record)) {

        $filebyUid   = $pMonitoring->getAudioByUniqueId($record);

        $file = basename($filebyUid['userfield']);
        $file = str_replace("audio:","",$file);

        $path = $path_record.$file;

        if($file[0] == "q"){// caso de archivos de colas no se tiene el tipo de archivo gsm, wav,etc
            $arrData  = glob("$path*");
            $path = isset($arrData[0])?$arrData[0]:$path;
        }

    // See if the file exists
        if ($file == 'deleted' || !is_file($path)) {
            return "";
        }

        $name = basename($path);

    //$extension = strtolower(substr(strrchr($name,"."),1));
        $extension=substr(strtolower($name), -3);

    // This will set the Content-Type to the appropriate setting for the file
        $ctype ='';
        switch( $extension ) {

            case "mp3": $ctype="audio/mpeg"; break;
            case "wav": $ctype="audio/x-wav"; break;
            case "Wav": $ctype="audio/x-wav"; break;
            case "WAV": $ctype="audio/x-wav"; break;
            case "gsm": $ctype="audio/x-gsm"; break;
            // not downloadable
            default: $ctype=""; break ;
        }
    }
    return $ctype;
}

function display_record($smarty, $module_name, $local_templates_dir, &$pDB, $pACL, $arrConf, $user, $extension, $esAdministrador){
    $action = getParameter("action");
    $file = getParameter("id");
    $namefile = getParameter('namefile');
    $pMonitoring = new paloSantoMonitoring($pDB);
    $path_record = $arrConf['records_dir'];
    $sContenido="";
    switch($action){
        case "display_record":
            if(!$esAdministrador){
                if(!$pMonitoring->recordBelongsToUser($file, $extension)){
                    $sContenido = _tr("You are not authorized to listen this file");
                }
            }
            if($sContenido == "")
                $session_id = session_id();
                $ctype=record_format($pDB, $arrConf);
                $sContenido=<<<contenido
                    <embed src='index.php?menu=$module_name&action=download&id=$file&namefile=$namefile&rawmode=yes&elastixSession=$session_id' width=300, height=20 autoplay=true loop=false type="$ctype"></embed><br>
contenido;
            break;
    }

    $smarty->assign("CONTENT", $sContenido);
    $smarty->display("_common/popup.tpl");
}

function deleteRecord($smarty, $module_name, $local_templates_dir, &$pDB, $pACL, $arrConf, $user, $extension, $esAdministrador)
{
    if(!$esAdministrador){
        $smarty->assign("mb_title", _tr("ERROR"));
        $smarty->assign("mb_message", _tr("You are not authorized to delete any records"));
        return reportMonitoring($smarty, $module_name, $local_templates_dir, $pDB, $pACL, $arrConf, $user, $extension, $esAdministrador);
    }
    $pMonitoring = new paloSantoMonitoring($pDB);
    $path_record = $arrConf['records_dir'];
    foreach($_POST as $key => $values){
        if(substr($key,0,3) == "id_")
        {
            $ID = substr($key, 3);
            $ID = str_replace("_",".",$ID);
            $recordName = $pMonitoring->getRecordName($ID);
            $record = substr($recordName,6);
            $record = basename($record);
            $path = $path_record.$record;
            if(is_file($path)){
                // Archivo existe. Se borra si se puede actualizar CDR
                if($pMonitoring->deleteRecordFile($ID))
                    unlink($path);
            } else {
                // Archivo no existe. Se actualiza CDR para mantener consistencia
                $pMonitoring->deleteRecordFile($ID);
            }
        }
    }

    $content = reportMonitoring($smarty, $module_name, $local_templates_dir, $pDB, $pACL, $arrConf, $user, $extension, $esAdministrador);
    return $content;
}

function SecToHHMMSS($sec)
{
    $HH = 0;$MM = 0;$SS = 0;
    $segundos = $sec;

    if( $segundos/3600 >= 1 ){ $HH = (int)($segundos/3600);$segundos = $segundos%3600;} if($HH < 10) $HH = "0$HH";
    if(  $segundos/60 >= 1  ){ $MM = (int)($segundos/60);  $segundos = $segundos%60;  } if($MM < 10) $MM = "0$MM";
    $SS = $segundos; if($SS < 10) $SS = "0$SS";

    return "$HH:$MM:$SS";
}

function createFieldFilter(){
    $arrFilter = array(
            "src"       => _tr("Source"),
            "dst"       => _tr("Destination"),
            "userfield" => _tr("Type"),
                    );

    $arrFormElements = array(
            "date_start"  => array(           "LABEL"                  => _tr("Start_Date"),
                                              "REQUIRED"               => "yes",
                                              "INPUT_TYPE"             => "DATE",
                                              "INPUT_EXTRA_PARAM"      => "",
                                              "VALIDATION_TYPE"        => "ereg",
                                              "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
            "date_end"    => array(           "LABEL"                  => _tr("End_Date"),
                                              "REQUIRED"               => "yes",
                                              "INPUT_TYPE"             => "DATE",
                                              "INPUT_EXTRA_PARAM"      => "",
                                              "VALIDATION_TYPE"        => "ereg",
                                              "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
            "filter_field" => array(          "LABEL"                  => _tr("Search"),
                                              "REQUIRED"               => "no",
                                              "INPUT_TYPE"             => "SELECT",
                                              "INPUT_EXTRA_PARAM"      => $arrFilter,
                                              "VALIDATION_TYPE"        => "text",
                                              "VALIDATION_EXTRA_PARAM" => ""),
            "filter_value" => array(          "LABEL"                  => "",
                                              "REQUIRED"               => "no",
                                              "INPUT_TYPE"             => "TEXT",
                                              "INPUT_EXTRA_PARAM"      => "",
                                              "VALIDATION_TYPE"        => "text",
                                              "VALIDATION_EXTRA_PARAM" => ""),
                    );
    return $arrFormElements;
}


function getAction()
{
    if(getParameter("save_new")) //Get parameter by POST (submit)
        return "save_new";
    else if(getParameter("action")=="display_record")
        return "display_record";
    else if(getParameter("submit_eliminar"))
        return "delete";
    else if(getParameter("action")=="download")
        return "download";
    else if(getParameter("action")=="view")   //Get parameter by GET (command pattern, links)
        return "view_form";
    else if(getParameter("action")=="view_edit")
        return "view_form";
    else
        return getParameter("action");
}

if (!function_exists('getParameter')) {
function getParameter($parameter)
{
    if(isset($_POST[$parameter]))
        return $_POST[$parameter];
    else if(isset($_GET[$parameter]))
        return $_GET[$parameter];
    else
        return null;
}
}
?>