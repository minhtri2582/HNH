<?php
  /* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 2.0.0-31                                               |
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
  $Id: index.php,v 1.1 2010-12-21 09:08:11 Alberto Santos asantos@palosanto.com Exp $ */
//include elastix framework
include_once "libs/paloSantoGrid.class.php";
include_once "libs/paloSantoForm.class.php";
include_once "libs/paloSantoConfig.class.php";
include_once "modules/extensions_batch/libs/paloSantoExtensionsBatch.class.php";
include_once "libs/misc.lib.php";

function _moduleContent(&$smarty, $module_name)
{
    //include module files
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "modules/$module_name/libs/paloSantoWeakKeys.class.php";

    //include file language agree to elastix configuration
    //if file language not exists, then include language by default (en)
    $lang=get_language();
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $lang_file="modules/$module_name/lang/$lang.lang";
    if (file_exists("$base_dir/$lang_file")) include_once "$lang_file";
    else include_once "modules/$module_name/lang/en.lang";

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
    $dsn_asterisk = generarDSNSistema("asteriskuser","asterisk");
    $pDB = new paloDB($dsn_asterisk);

    $pConfig = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
    $arrAMP  = $pConfig->leer_configuracion(false);

    $pConfig = new paloConfig($arrAMP['ASTETCDIR']['valor'], "asterisk.conf", "=", "[[:space:]]*=[[:space:]]*");
    $arrAST  = $pConfig->leer_configuracion(false);

    //actions
    $action = getAction();
    $content = "";

    switch($action){
        case "change":
            $content = editWeakKeys($smarty, $module_name, $local_templates_dir, $arrConf, $pDB);
            break;
        case "save":
            $content = saveNewKey($smarty, $module_name, $local_templates_dir, $pDB, $arrConf, $arrAST, $arrAMP);
            break;
        default:
            $content = reportWeakKeys($smarty, $module_name, $local_templates_dir, $pDB, $arrConf);
            break;
    }
    return $content;
}

function reportWeakKeys($smarty, $module_name, $local_templates_dir, &$pDB, $arrConf)
{
    $pWeakKeys = new paloSantoWeakKeys($pDB);
    $filter_field = getParameter("filter_field");
    $filter_value = getParameter("filter_value");
    //begin grid parameters
    $oGrid  = new paloSantoGrid($smarty);    
    $total = $pWeakKeys->getNumWeakKeys($filter_field,$filter_value);
    $pDB2 = new paloDB($arrConf['elastix_dsn']['acl']);
    $pACL = new paloACL($pDB2);
    $oGrid->enableExport();   // enable csv export.
    $oGrid->pagingShow(true); // show paging section.
    $oGrid->setTitle(_tr("Weak Secrets"));
    $oGrid->setIcon("modules/$module_name/images/security_weak_keys.png");
    $oGrid->setNameFile_Export(_tr("Weak Secrets"));
    if($oGrid->isExportAction()){
        $limit = $total;
        $offset = 0;
        $bExportation = true;
    }
    else{
        $limit  = 30;
        $oGrid->setLimit($limit);
        $oGrid->setTotal($total);
        $offset = $oGrid->calculateOffset();
        $bExportation = false;
    }
    $url = array(
        'menu'          =>  $module_name,
        'filter_field'  =>  $filter_field,
        'filter_value'  =>  $filter_value,
    );
    $oGrid -> setURL($url);
    $arrResult =$pWeakKeys->getWeakKeys($limit,$offset,$filter_field,$filter_value);
    $arrData = null;
    //$arrResult =$pWeakKeys->getWeakKeysChecker();
    $arrColumns = array(_tr("Extension"),_tr("Description"),_tr("Status"));
    $oGrid->setColumns($arrColumns);
    if(is_array($arrResult) && $total>0){
        foreach($arrResult as $tech){
            foreach($tech as $key => $value){ 
            $arrTmp[0] = $value['id'];
            $arrTmp[1] = $value['description'];
            $mensaje = getMensaje($value['id'],$value['data']);
            if($mensaje != "OK" && !$bExportation)
                if($pACL->isUserAdministratorGroup($_SESSION['elastix_user']) || $pACL->getUserExtension($_SESSION['elastix_user'])==$value['id'])
                    $mensaje = _tr("Weak Key").": $mensaje &nbsp;<a href='?menu=$module_name&action=change&id=$value[id]'>"._tr("Change Secret")."</a>";
            $arrTmp[2] = $mensaje;
            $arrData[] = $arrTmp;
            }
        }
    }
    $oGrid->setData($arrData);

    //begin section filter
    $arrFormFilterWeakKeys = createFieldFilter();
    $oFilterForm = new paloForm($smarty, $arrFormFilterWeakKeys);
    $smarty->assign("SHOW", _tr("Show"));

    $_POST["filter_field"]= $filter_field; 
    $_POST["filter_value"]= $filter_value;

    $oGrid->addFilterControl(_tr("Filter applied: ")._tr("Extension")." = ".$filter_value,$_POST, array("filter_field" => "extension","filter_value" => ""));
    $htmlFilter = $oFilterForm->fetchForm("$local_templates_dir/filter.tpl","",$_POST);
    //end section filter
    $oGrid->showFilter(trim($htmlFilter));
    $content = $oGrid->fetchGrid();
 
    //end grid parameters

    return $content;
}

function editWeakKeys($smarty, $module_name, $local_templates_dir,$arrConf, $pDB, $Extension = "")
{
    $id = getParameter("id");
    if($id == "")
        $id = $Extension;
    $pDB2 = new paloDB($arrConf['elastix_dsn']['acl']);
    $pACL = new paloACL($pDB2);
    $pWeakKeys = new paloSantoWeakKeys($pDB);
    if(!$pACL->isUserAdministratorGroup($_SESSION['elastix_user']))
        if($pACL->getUserExtension($_SESSION['elastix_user']) != $id){
            $smarty->assign("mb_title",_tr("Error"));
            $smarty->assign("mb_message",_tr("You are not authorized to enter to that extension"));
            return reportWeakKeys($smarty, $module_name, $local_templates_dir, $pDB, $arrConf);
        }
    $arrFormRules = createFieldForm();
    $oForm = new paloForm($smarty,$arrFormRules);
    $smarty->assign("SAVE", _tr("Save"));
    $smarty->assign("CANCEL", _tr("Cancel"));
    $smarty->assign("REQUIRED_FIELD", _tr("Required field"));
    if($pACL->isUserAdministratorGroup($_SESSION['elastix_user']))
        $smarty->assign("DISPLAY", "display:none;");
    $smarty->assign("icon", "images/list.png");
    $arrValues['Extension'] = $id;
    $htmlForm = $oForm->fetchForm("$local_templates_dir/change.tpl",_tr("Change Key"), $arrValues);
    $contenidoModulo = "<form  method='POST' style='margin-bottom:0;' action='?menu=$module_name'>".$htmlForm."</form>";
    return $contenidoModulo;
}

function createFieldForm()
{
    $arrFields = array(
            "Extension"       => array( "LABEL"                  => _tr("Extension"),
                                        "REQUIRED"               => "no",
                                        "INPUT_TYPE"             => "TEXT",
                                        "INPUT_EXTRA_PARAM"      => array("style" => "width:90px", "readonly" => "readonly"),
                                        "VALIDATION_TYPE"        => "text",
                                        "VALIDATION_EXTRA_PARAM" => "",
                                        "EDITABLE"               => "no",
                                            ),
            "Current_Secret"   => array( "LABEL"                 => _tr("Current Secret"),
                                        "REQUIRED"               => "no",
                                        "INPUT_TYPE"             => "PASSWORD",
                                        "INPUT_EXTRA_PARAM"      => array("style" => "width:90px"),
                                        "VALIDATION_TYPE"        => "text",
                                        "VALIDATION_EXTRA_PARAM" => "",
                                        "EDITABLE"               => "yes",
                                            ),
            "New_Secret"         => array( "LABEL"               => _tr("New Secret"),
                                        "REQUIRED"               => "yes",
                                        "INPUT_TYPE"             => "PASSWORD",
                                        "INPUT_EXTRA_PARAM"      => array("style" => "width:90px"),
                                        "VALIDATION_TYPE"        => "ereg",
                                        "VALIDATION_EXTRA_PARAM" => "^[[:alnum:]]{5,}$",
                                        "EDITABLE"               => "",
                                            ),
            "Confirm_New_Secret" => array( "LABEL"               => _tr("Confirm New Secret"),
                                        "REQUIRED"               => "yes",
                                        "INPUT_TYPE"             => "PASSWORD",
                                        "INPUT_EXTRA_PARAM"      => array("style" => "width:90px"),
                                        "VALIDATION_TYPE"        => "ereg",
                                        "VALIDATION_EXTRA_PARAM" => "^[[:alnum:]]{5,}$",
                                        "EDITABLE"               => "",
                                            ),
            );
    return $arrFields;
}

function saveNewKey($smarty, $module_name, $local_templates_dir, $pDB, $arrConf, $arrAST, $arrAMP)
{
    $arrFormNew = createFieldForm($pDB);
    $arrValues['id'] = getParameter("Extension");
    $arrValues['key'] = getParameter("Current_Secret");
    $arrValues['new_key'] = getParameter("New_Secret");
    $confirmation = getParameter("Confirm_New_Secret");
    $pDB2 = new paloDB($arrConf['elastix_dsn']['acl']);
    $pACL = new paloACL($pDB2);
    $oForm = new paloForm($smarty, $arrFormNew);
    if(!$oForm->validateForm($_POST)) {
        // Falla la validación básica del formulario
        $strErrorMsg = "<b>"._tr('The following fields contain errors').":</b><br/>";
        $arrErrores = $oForm->arrErroresValidacion;
        if(is_array($arrErrores) && count($arrErrores) > 0){
            foreach($arrErrores as $k=>$v) {
                $strErrorMsg .= "$k: [$v[mensaje]] <br /> ";
            }
        }
        $smarty->assign("mb_title", _tr("Validation Error"));
        $smarty->assign("mb_message", $strErrorMsg);
        return editWeakKeys($smarty, $module_name, $local_templates_dir, $arrConf, $pDB, $arrValues['id']);
    }

    $pWeakKeys = new paloSantoWeakKeys($pDB);
    $device = $pWeakKeys->getWeakKeyById($arrValues['id']);
    if(!$pACL->isUserAdministratorGroup($_SESSION['elastix_user']))
        if($arrValues['key'] != $device['data']){
            $smarty->assign("mb_title", _tr("Error"));
            $smarty->assign("mb_message", _tr("The Current Secret is invalid"));
            return editWeakKeys($smarty, $module_name, $local_templates_dir, $arrConf, $pDB, $arrValues['id']);
        }
    if($arrValues['new_key'] != $confirmation){
        $smarty->assign("mb_title", _tr("Error"));
        $smarty->assign("mb_message", _tr("The New Secret does not match with the Confirmation Secret"));
        return editWeakKeys($smarty, $module_name, $local_templates_dir, $arrConf, $pDB, $arrValues['id']);
    }
    $mensaje = getMensaje($arrValues['id'],$arrValues['new_key']);
    if($mensaje != "OK"){
        $smarty->assign("mb_title", _tr("Error"));
        $smarty->assign("mb_message", $mensaje);
        return editWeakKeys($smarty, $module_name, $local_templates_dir, $arrConf, $pDB, $arrValues['id']);
    }
    if(!$pWeakKeys->saveNewKey($arrValues,$device['tech'])){
        $smarty->assign("mb_title", _tr("Error"));
        $smarty->assign("mb_message", $pWeakKeys->errMsg);
        return editWeakKeys($smarty, $module_name, $local_templates_dir, $arrConf, $pDB, $arrValues['id']);
    }
    $data_connection = array('host' => $arrConf['AMI_HOST'], 'user' => $arrConf['AMI_USER'], 'password' => $arrConf['AMI_PASS']);
    $pLoadExtension = new paloSantoLoadExtension($pDB);
    if(!$pLoadExtension->do_reloadAll($data_connection, $arrAST, $arrAMP)){
        $smarty->assign("mb_title", _tr("Error"));
        $smarty->assign("mb_message", $pLoadExtension->errMsg);
        return editWeakKeys($smarty, $module_name, $local_templates_dir, $arrConf, $pDB, $arrValues['id']);
    }
    $smarty->assign("mb_title", _tr("Message"));
    $smarty->assign("mb_message", _tr("Successful Secret Update"));
    return  reportWeakKeys($smarty, $module_name, $local_templates_dir, $pDB, $arrConf);
}

function createFieldFilter(){
    $arrFilter = array(
        "extension" => _tr("Extension"),
                    );

    $arrFormElements = array(
            "filter_field" => array("LABEL"                  => _tr("Search"),
                                    "REQUIRED"               => "no",
                                    "INPUT_TYPE"             => "SELECT",
                                    "INPUT_EXTRA_PARAM"      => $arrFilter,
                                    "VALIDATION_TYPE"        => "text",
                                    "VALIDATION_EXTRA_PARAM" => ""),
            "filter_value" => array("LABEL"                  => "",
                                    "REQUIRED"               => "no",
                                    "INPUT_TYPE"             => "TEXT",
                                    "INPUT_EXTRA_PARAM"      => "",
                                    "VALIDATION_TYPE"        => "text",
                                    "VALIDATION_EXTRA_PARAM" => ""),
                    );
    return $arrFormElements;
}

function getMensaje($extension,$key)
{
    $mensaje = "";
    if($extension == $key)
        $mensaje.=_tr("Same Key");
    if(strlen($key) < 5)
        if($mensaje == "")
            $mensaje.=_tr("Short Key");
        else
            $mensaje.=", "._tr("Short Key");
    if($mensaje == "")
        $mensaje = "OK";
    return $mensaje;
}

function getAction()
{
    if(getParameter("action") == "change")
        return "change";
    else if(getParameter("save"))
        return "save";
    else
        return "report"; //cancel
}
?>
