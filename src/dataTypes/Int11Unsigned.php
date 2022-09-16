<?php
namespace obray\data\types;

class Int11Unsigned extends BaseType
{
    const IS_PRIMARY = false;
    const TYPE = 'INT';
    const LENGTH = 11;
    const UNSIGNED = true;
    const NULLABLE = false;
}