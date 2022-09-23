<?php
namespace obray\data\types;

class Password extends Varchar255Nullable
{
    const DEFAULT = null;

    static public function hash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

}