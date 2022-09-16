<?php
namespace obray\data\types;

class Int11UnsignedNullable extends BaseType
{
    const IS_PRIMARY = false;
    const TYPE = 'INT';
    const LENGTH = 11;
    const UNSIGNED = true;
    const NULLABLE = true;
    const DEFAULT = null;
    const AUTO_INCEMENT = false;
}