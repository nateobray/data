<?php
namespace obray\data\types;

class Password extends Varchar255Nullable
{
    const DEFAULT = null;

    static public function hash($password)
    {
        $salt = '$2a$12$' . self::generateToken();
        return crypt($password, $salt);
    }

    static private function generateToken()
    {
        $safe = false;
        return hash('sha512', base64_encode(openssl_random_pseudo_bytes(128, $safe)));
        if(!$safe) throw new \Exception("Unable to generate safe token.");
    }
}