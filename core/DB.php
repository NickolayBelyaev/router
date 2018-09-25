<?php

class DB {

    function connect($db_kind,$db,$srv,$dbname)
    {
        var_dump($db_kind);var_dump($db);var_dump($srv);var_dump($dbname);        
//        try {
//            if ($db_kind == 'pg'){
                
//                $conn = new PDO("pgsql:dbname={$dbname};host={$db[$url]['host']};port=5432",$db[$url]['username'],$db[$url]['password']); 
                $conn = new PDO(
                            "pgsql:dbname={$db[$srv]['dbname']};host={$db[$srv]['host']};port=5432",
                            $db[$srv]['username'],
                            $db[$srv]['password']
                            );                 
                // set the PDO error mode to exception
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $conn;
//            } elseif ($db_kind == 'my') {
//                return $conn;
//            }
//            
//        } catch (PDOException $exception) {
//            exit($exception->getMessage());
//        }
    }
}