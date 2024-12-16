<?php

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");
if (!$USER->IsAdmin()) {
    LocalRedirect('/');
}

require 'parser.php';

if (!isset($_GET['iblockId'])) {
    throw new Exception("Не указан ID инфоблока");
}

$iblockId = $_GET['iblockId'];

if (!isset($_GET['dataFile'])) {
    throw new Exception("Не указан файл с данными");
}

$dataFile = $_GET['dataFile'];

$parser = new Parser($USER->GetID(), $iblockId, $_GET['dataFile']);
$parser->start();

