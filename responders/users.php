<?php
require_once (dirname(__DIR__) . '/core/DBManager.php');
function loginUser($data){
    $db = new DBManager();
    $dbpdo = $db->getPDO();
    
}