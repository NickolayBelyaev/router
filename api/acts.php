<?php

$configDb = 'config';
$configTable = 'db';
try {
  $dbInit = new PDO(
          "pgsql:dbname={$dbConfig[$configDb]['dbname']};host={$dbConfig[$configDb]['host']};"
          . "port=5432", $dbConfig[$configDb]['username'], $dbConfig[$configDb]['password']
  );
  $dbInit->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $ex) {
  $return['status'] = 'error';
  $return['message'] = "Database config failed: {$e->getMessage()}";
  echo json_encode($result);
  exit;
}
$dbList = getRecords($dbInit, $configTable, NULL);
$db = [];
foreach ($dbList as $fldName => $value) {
  $db[$value['alias']] = ['host' => $value['host'],
      'dbname' => $value['dbname'],
      'username' => $value['username'],
      'password' => $value['password']
  ];
}

$method = filter_input(INPUT_SERVER, "REQUEST_METHOD");
$url = parse_url(filter_input(INPUT_SERVER, "REQUEST_URI"), PHP_URL_PATH);
$query = array();
parse_str(filter_input(INPUT_SERVER, "QUERY_STRING"), $query);

// checking if slash is first character in route otherwise add it
if (strpos($url, "/") !== 0) {
  $url = "/$url";
}

if ($method == 'POST' || $method == 'PATCH') {
  $input = json_decode(file_get_contents('php://input'));
  if (!$input) {
    parse_str(file_get_contents('php://input'), $input);
  }
} else {
  $input = "";
}

$urls = explode("/", $url);
$dbInstance = new DB();

try {
  $dbConn = new PDO(
          "pgsql:dbname={$db[$urls[1]]['dbname']};host={$db[$urls[1]]['host']};port=5432", $db[$urls[1]]['username'], $db[$urls[1]]['password']
  );
  // set the PDO error mode to exception
  $dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  $return['status'] = 'error';
  $return['message'] = "Database connection failed: {$e->getMessage()}";
  echo json_encode($result);
}

$colunns = getColumnNames($dbConn, $urls[2]);


rout($method, $dbConn, $urls, $query, $input);

// ---------------------------------------------------------------------------
//@params:  
//  string $method   - 'GET', 'POST', 'PATCH', 'DELETE'
//  PDO    $dbConn,
//  array  $urls     - array with components from URL
//    string $urls[2]  - table name
//    int    $urls[3]  - id numer of record
//    string $urls[4]  - releting table|view name
//  array  $query    - URL component after the question mark ?
//  array  $input    - pathed data,
function rout($method, $dbConn, $urls, $query, $input) {
  $return['status'] = 'error';
  switch ($method) {
    //Code to get records. Method GET
    case 'GET':
      if (isset($urls[3])) {
        if (array_key_exists('filter', $query) &&
                (stristr($query['filter'], 'id=') === FALSE)) {
          $query['filter'] = "{$query['filter']} and id={$urls[3]}";
        } else {
          $query['filter'] = "id={$urls[3]}";
        }
      }
      if ($result = getRecords($dbConn, $urls[2], $query)) {
        $return['status'] = 'ok';
        $return['data'] = array_values($result);
      } else {
        $return['message'] = "data not found";
      }
      break;
    case 'POST':
      //Insert Post record based on ID @param: $input, $db
      $recordId = addRecord($input, $dbConn, $urls[2]);
      if ($recordId) {
        $return['status'] = 'ok';
        $return['data'] = $recordId;
      }
      break;
    case 'PATCH':
      //Code to update post, if /acts/{id} and method is PATCH. Calls 
      if (isset($urls[3])) {
        if ($result = updateRecords($input, $dbConn, $urls[2], $urls[3])) {
          $return['status'] = 'ok';
//          $return['id'] = $result;
        } else {
          $return['message'] = "{$method} update datas is not correct";
        }
      } else {
        $return['message'] = "{$method} don't passed id record";
      }

      break;
    //Code to delete post, if /acts/{id} and method is DELETE. Calls deletePost() 
    case 'DELETE':
      if (isset($urls[3])) {
        if (deleteRecord($dbConn, $urls[2], $urls[3])) {
          $return['status'] = 'ok';
        } else {
          $return['message'] = 'object not found';
        }
      } else {
        $return['message'] = "{$method} input is not correct";
      }
      break;
    default:
      $return['status'] = 'error';
      $return['message'] = "{$method} is wrong method";
      break;
  }
  echo json_encode($return);
}

// ---------------------------------------------------------------------------
//@params:  
//  $db, 
//  $tbl_name, 
//  $query
function getRecords($db, $tbl_name, $query) {
  try {
    $fiels = "*";
    $limit = '';
    $offset = '';
    $where = '';
    $sort = '';
    $desc = '';
    if (isset($query)) {
      foreach ($query as $key => $value) {
        switch (strtolower($key)) {
          case "offset":
            $offset = " OFFSET {$value}";
            break;
          case "limit":
            $limit = " LIMIT {$value}";
            break;
          case "sort":
            switch (substr($value, 0, 1)) {
              case '-':
                $desc = " DESC";
              case '+':
                $value = substr($value, 1);
            };
            $sort = " ORDER BY {$value}{$desc}";
            break;
          case "fields":
            $fiels = $value;
            break;
          case "filter":
            $where = " WHERE {$value}";
            break;
          default:
            return false;
        }
      }
    }
    $sql = "SELECT {$fiels} FROM {$tbl_name}{$where}{$sort}{$limit}{$offset}";
//    echo "{$sql}\n";
    $statment = $db->prepare($sql);
    try {
      $statment->execute();
    } catch (PDOException $ex) {
      $return['status'] = 'error';
      $return['message'] = "SELECT {$ex->getMessage()}";
      die(json_encode($return));
    }
    $statment->setFetchMode(PDO::FETCH_ASSOC);
    return $statment->fetchAll();
  } catch (PDOException $ex) {
    $return['status'] = 'error';
    $return['message'] = "SELECT {$ex->getMessage()}";
    die(json_encode($return));
  }
}

//Insert recorn in table $tbl_name 
// @param: 
//    $input,
//    $db,
//    $tbl_name 
// @return: integer - 1 or 0
function addRecord($input, $db, $tbl_name) {
  try {
    $fields = '';
    $values = '';
    $i = 0;
    $allowedFields = array();
    foreach ($input as $fldName => $value) {
      $str = strtolower($fldName);
      if ($i > 0) {
        $fields .= ",";
        $values .= ",";
      }
      $allowedFields[$i] = $fldName;
      $fields .= " {$str}";
      $values .= " :{$str}";
      $i++;
    }

    //Get index for $tbl_name
    $sqlGetKey = "SELECT a.attname, format_type(a.atttypid, a.atttypmod) AS data_type"
            . " FROM   pg_index i"
            . " JOIN   pg_attribute a ON a.attrelid = i.indrelid"
            . " AND a.attnum = ANY(i.indkey)"
            . " WHERE  i.indrelid = '{$tbl_name}'::regclass"
            . " AND    i.indisprimary";
    $returning = '';
    try {
      $st = $db->prepare($sqlGetKey);
      $st->execute();
      $r = $st->fetchAll();
      $ri = array_column($r, 'attname');
      $coma = '';
      $indexFields = '';
      foreach ($ri as $value) {
        $indexFields = "{$indexFields}{$coma}{$value}";
        $coma = ',';
      }
      $returning = " RETURNING {$indexFields}";
    } catch (PDOException $ex) {
      echo "{$ex->getMessage()}\n";
    }
    if ($returning == '') {
      $returning = ' RETURNING oid';
    }
    //Inset record
    $sql = "INSERT INTO {$tbl_name} ({$fields}) VALUES ({$values}){$returning}";
//    echo "$sql\n";
    $statement = $db->prepare($sql);
    bindAllValues($statement, $input, $allowedFields);
    if ($statement->execute()) {
      try {
        $r = [];
        foreach ($statement->fetch() as $fldName => $value){
          if (!is_int($fldName)) {
           $r[$fldName] = $value;
          }
        }
      } catch (PDOException $ex) {
        $r = (array_key_exists('id', $input)) ? (int) $input['id'] : NULL;
      }
      return $r;
    } else {
      return false;
    }
    return $r;
  } catch (PDOException $ex) {
    $r['status'] = 'error';
    $r['message'] = "INSERT {$ex->getMessage()}";
    echo json_encode($r);
  }
}

function bindAllValues($statement, $params, $allowedFields) {
  foreach ($params as $param => $value) {
    if (in_array($param, $allowedFields)) {
      $statement->bindValue(':' . $param, $value);
    }
  }
  return $statement;
}

//Update recorn in table $tbl_name 
//  @params: 
//    array  $input,
//    PDO    $db,
//    striing $tbl_name,
//    $id 
//    @return integer
function updateRecords($input, $db, $tbl_name, $id) {
  $fields = '';
  $values = '';
  $allowedFields = array();
  $i = 0;
  foreach ($input as $fldName => $value) {
    $str = strtolower($fldName);
    if ($i > 0) {
      $fields .= ",";
    }
    $allowedFields[$i] = $fldName;
    $fields .= " {$str} = :{$str}";
    $i++;
  }

  $sql = "UPDATE {$tbl_name} SET {$fields} WHERE id={$id}";
//  echo "{$sql}\n";
  try {
    $statement = $db->prepare($sql);
    bindAllValues($statement, $input, $allowedFields);
    $statement->execute();
    return ($statement->rowCount());
  } catch (PDOException $ex) {
    $return['status'] = 'error';
    $return['message'] = "UPDATE {$ex->getMessage()}";
    echo json_encode($return);
  }
}

// Deletes from table $tbl_name record based on ID 
// @params: 
//    PDO     $db,
//    string  $tbl_name,
//    integer $id
// @return integer

function deleteRecord($db, $tbl_name, $id) {
  try {
    $statement = $db->prepare("DELETE FROM {$tbl_name} WHERE id=:id");
    $statement->bindValue(':id', $id, PDO::PARAM_INT);
    $statement->execute();
    return ($statement->rowCount());
  } catch (PDOException $ex) {
    $return['status'] = 'error';
    $return['message'] = "{$id} DELETE {$ex->getMessage()}";
    echo json_encode($return);
  }
}

//Returns colun names for @param:$db, $tbl_name
// @params: 
//  PDO     $db,
//  string  $tbl_name,
function getColumnNames($db, $tbl_name) {
  try {
    $sql = "select  column_name from information_schema.columns" .
            " where table_schema='public' and table_name='{$tbl_name}'"
    ;
    $statement = $db->prepare($sql);
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    $ret = array();
    $i = 0;
    foreach ($statement->fetchAll() as $param => $value) {
      $ret[$i++] = $value["column_name"];
    }
    return $ret;
  } catch (PDOException $ex) {
    $return['status'] = 'error';
    $return['message'] = "{$tbl_name} {$ex->getMessage()}";
    echo json_encode($return);
  }
}
