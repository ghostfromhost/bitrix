<?php
namespace MIET\KPI;
use Bitrix\Main\Application;
use Bitrix\Main\Entity;
use Bitrix\Main\Entity\Event;
use Bitrix\Main\Localization\Loc;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\UserTable;
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;

const EMPLOYEE_HL_BLOCK_ID = 2;
//���������� ������ highloadblock
\CModule::IncludeModule('highloadblock');
//������� ������� ��������� ���������� ������:
function GetEntityDataClass($HlBlockId) {
	if (empty($HlBlockId) || $HlBlockId < 1)
	{
		return false;
	}
	$hlblock = HLBT::getById($HlBlockId)->fetch();	
	$entity = HLBT::compileEntity($hlblock);
	$entity_data_class = $entity->getDataClass();
	return $entity_data_class;
}


Loc::loadMessages(__FILE__);
class KPIManager {
const IBLOCK_CODE_KPI = 'kpi';
const IBLOCK_CODE_DEPARTMENTS = 'departments';

public static function getHLEmployee($employee) {
	$entity_data_class = GetEntityDataClass(EMPLOYEE_HL_BLOCK_ID);
	$rsData = $entity_data_class::getList(array(
	   'select' => array('*'),
	   'order' => array('UF_PERIOD' => 'ASC'),
	   'filter' => array('UF_EMPLOYEE' => $employee) 
	));
	return $rsData->fetchAll();
}


public static function GetKPI(
$arOrder = array('SORT' => 'ASC'),
$arFilter = array(),
$arGroupBy = false,
$arNavStartParams = false,
$arSelectFields = array('ID', 'NAME')
) {
$elements = array();
//�������� ID ��������� KPI �� ��� ����������� ����
$rsIblock = \CIBlock::GetList(
array(),
array('CODE' => self::IBLOCK_CODE_KPI, 'SITE_ID' => SITE_ID)
);
$arIblock = $rsIblock->GetNext();
$arFilter['IBLOCK_ID'] = $arIblock['ID'];
$rsElements = \CIBlockElement::GetList(
$arOrder, //������ ����� ���������� ��������� � ������������
$arFilter, //������ ����� ������� ��������� � �� ��������
$arGroupBy, //������ ����� ��� ����������� ���������
$arNavStartParams, //��������� ��� ������������ ���������� ����������� ���������� ��������� ���������
$arSelectFields //������ ������������ ����� ���������
);
while($arElements = $rsElements->Fetch()) {
//��������� ���������� � ����� � ����������� ������� ����������: ������ �� ���� �� �������, �������� ����� � �.�.
foreach($arElements['PROPERTY_REGULATIONS_VALUE'] as $key => $idFileRegulation) {
	$arElements['PROPERTY_REGULATIONS_VALUE'][$key] = \CFile::GetFileArray($idFileRegulation);
}

$elements[] = $arElements;
}
return $elements;
}
public static function GetKPIEmployee($idEmployee) {
if(!$idEmployee) {
return array();
}
//�������� ������ ���� ������������� ����������
$arDepartmentsUser = UserTable::getList(array(
'select' => array(
'UF_DEPARTMENT'
),
'filter' => array(
'ID' => $idEmployee
)
))->fetch();
var_dump($arDepartmentsUser);
//�������� ������ ���� KPI ������ �������������
return self::GetKPI(
array('NAME' => 'asc'),
array('PROPERTY_DEPARTMENT.ID' => $arDepartmentsUser),
false,
false,
array('ID', 'NAME', 'PROPERTY_INDICATOR_TYPE',
'PROPERTY_WEIGHT', 'PROPERTY_REGULATIONS')
);
}


public static function EditKPIEmployee($arKPIValues, $arKPIId) {
	if(!count($arKPIId) || !count($arKPIValues)) {
		return array();
	}
	foreach ($arKPIValues as $KPI => $KPIValue) {
		$entity_data_class = GetEntityDataClass(EMPLOYEE_HL_BLOCK_ID);
		$result = $entity_data_class::update($arKPIId[$KPI], array(
		  'UF_VALUE'=> $KPIValue
		));
	}
}


public static function SetKPIEmployee($idEmployee, $period, $arKPIValues) {
if(!$idEmployee || !is_array($arKPIValues) || !count($arKPIValues)) {
return array();
}
global $USER;
//�������� ������ ����������� � ��
$db = Application::getConnection();
//�������� ����������
$db->startTransaction();
foreach($arKPIValues as $KPI => $KPIValue) {
$arValue = array(
'UF_VALUE' => $KPIValue,
'UF_KPI' => $KPI,
'UF_EMPLOYEE' => $idEmployee,
'UF_CREATED_BY' => $USER->GetID(),
'UF_CREATED' => new \Bitrix\Main\Type\DateTime(date('d.m.Y') . ' 00:00:00'),
'UF_PERIOD' => new \Bitrix\Main\Type\DateTime($period. ' 00:00:00')
);
$result = KPIEmployeeTable::add($arValue);
if (!$result->isSuccess()) {
$db->rollbackTransaction();
return false;
}
}
if ($result->isSuccess()) {
$db->commitTransaction();
return true;
}
}
}