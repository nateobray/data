<?php
namespace obray\data\types;

class TinyInt1Unsigned extends Tinyint1
{
    const TYPE = 'TINYINT';
    const LENGTH = 1;
    const UNSIGNED = true;
    const NULLABLE = false;
}