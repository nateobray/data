<?php
namespace obray\data\types;

class DateTimeModified extends BaseType
{
    const TYPE = 'DATETIME';
    const LENGTH = null;
    const UNSIGNED = false;
    const NULLABLE = true;
    const DEFAULT = NULL;
    const ON_UPDATE = 'CURRENT_TIMESTAMP';

}