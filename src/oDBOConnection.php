<?php

        /*****************************************************************************

	The MIT License (MIT)

	Copyright (c) 2014 Nathan A Obray <nathanobray@gmail.com>

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the 'Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	*****************************************************************************/

        Class oDBOConnection {

                private $username;
                private $password;
                private $host;
                private $port;
                private $db_name;

                private $conn;

                public function __construct($host,$username,$password,$db_name,$port='3306'){
                        $this->host = $host;
                        $this->username = $username;
                        $this->password = $password;
                        $this->db_name = $db_name;
                        $this->port = $port;
                }

                public function setUsername( string $username ){
                        $this->username = $username;
                }

                public function setPassword( string $password ){
                        $this->password = $password;
                }

                public function setHost( $host ){
                        $this->host = $host;
                }

                public function setPort( $port ){
                        $this->port = $port;
                }

                public function setDBName( $name ){
                        $this->db_name = $name;
                }

                public function establishConnection( $reconnects=FALSE ){
                        
                        if( !isSet( $this->conn ) || $reconnect ){
                                try {
                                        $this->conn = new PDO(
                                                'mysql:host='.$this->host.';dbname='.$this->db_name.';charset=utf8',
                                                $this->username,
                                                $this->password,
                                                array(
                                                        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
                                                ));
                                        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                } catch(PDOException $e) {
                                        echo 'ERROR: ' . $e->getMessage(); 
                                        exit();
                                }
                        }

                }

                public function getConnection(){
                        return $this->conn;
                }

        }