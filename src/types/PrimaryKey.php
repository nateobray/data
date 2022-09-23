<?php 
namespace obray\data\types;

class PrimaryKey extends BaseType
{
    const IS_PRIMARY = true;
    const TYPE = 'INT';
    const LENGTH = 11;
    const UNSIGNED = true;
    const NULLABLE = false;
    const DEFAULT = null;
    const AUTO_INCEMENT = true;
}