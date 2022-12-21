<?php
namespace obray\data\types;

class DateTimeCreated extends DateTime
{
    const TYPE = 'DATETIME';
    const LENGTH = null;
    const UNSIGNED = false;
    const NULLABLE = false;
    const DEFAULT = 'CURRENT_TIMESTAMP';   
}