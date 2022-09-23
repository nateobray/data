<?php
namespace obray\data\types;

class TinyInt1UnsignedNullable extends Tinyint1
{
    const TYPE = 'TINYINT';
    const LENGTH = 1;
    const UNSIGNED = true;
    const NULLABLE = true;
}