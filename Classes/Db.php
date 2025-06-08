<?php
namespace Classes;

use mysqli_result;

require_once(__DIR__.'/../config/functions.php');

class Db{
    private $db;
    private $doctrinedb;
    public function __construct(){
        mysqli_report(MYSQLI_REPORT_ERROR);
        $this->doctrinedb = db();
        $this->db = $this->doctrinedb->getNativeConnection();
    }

    public function __destruct(){
    }

    public function exe($sql, $array=array(), $returnFalseIfNoAffectedRows = false, $getAffectedRows = false){

        sqln();
        
        $params = '';

        $stmt = $this->db->prepare($sql);

        if(!$stmt) {
            echo $sql . ' - ' . $this->db->error;
            exit('error stmt: check $sql');
        }

        // str to array
        if(!is_array($array)) {
            $values = array(&$array);
        } else {
            $values = $array;
        }

        // if values
        if(count($values)) {
            // params type
            foreach($values as $key => $value) {
                if(is_numeric($value)) {
                    $params .= 'i';
                } else {
                    $params .= 's';
                }
                // Make sure each element is passed by reference
                $values[$key] = &$values[$key];
            }

            // add params BEFORE $values
            array_unshift($values, $params);

            // execute $stmt->bind_param($params, $values...)
            call_user_func_array(array($stmt, 'bind_param'), $values);
        }

        $stmt->execute();

        if ($stmt->errno > 0) {
            exit('error stmt: '.$stmt->error);
        }

        $res = $stmt->get_result();

        if($res) {
            return $res;
        }
        if($returnFalseIfNoAffectedRows && $stmt->affected_rows == 0) {
            return false;
        }
        if ($getAffectedRows) {
            return $stmt->affected_rows;
        }

        return true;
    }



    public function get_single($table, $id, $fields=array()) : mysqli_result{

        $select = (count($fields)) ? '`'. implode('`,`', $fields) .'`' : '*';

        $sql = '
        SELECT
        '. $select .'
        FROM
        '. $table .'
        WHERE
        id = ?
        ';

        $params = array(&$id);

        $res = $this->exe($sql, $params);

        return $res;
    }

    public function get_single_player_id($table, $id, $fields=array()) : mysqli_result{

        $select = (count($fields)) ? '`'. implode('`,`', $fields) .'`' : '*';

        $sql = '
        SELECT
        '. $select .'
        FROM
        '. $table .'
        WHERE
        player_id = ?
        ';

        $params = array(&$id);

        $res = $this->exe($sql, $params);

        return $res;
    }

    public function get_count($sql) : int{

        $res = $this->exe($sql);

        $row = $res->fetch_assoc();

        return $row['n'];
    }

    public function insert($table, $values) : bool{

        $fields = $args = array();

        foreach($values as $k=>$e){

            $fields[] = $k;

            $args[] = '?';

            $valuesRef[] = &$values[$k];
        }

        $sql = '
        INSERT INTO
        '. $table .'
        (
            `'. implode('`,`', $fields) .'`
        )
        VALUES
        (
            '. implode(', ', $args) .'
        );
        ';

        return $this->exe($sql, $valuesRef);

    }

    public function delete($table, $values){

        $fields = array();

        foreach($values as $k=>$e){

            $fields[] = $k .' = ?';

            $valuesRef[] = &$values[$k];
        }

        $sql = '
        DELETE FROM
        '. $table .'
        WHERE
        '. implode(' AND ', $fields) .'
        ';

        return $this->exe($sql, $valuesRef);
    }


    // last id
    public function get_last_id($table, $order = 0){

        // order id ?
        $order = (!empty($order)) ? 'ASC' : 'DESC';

        $sql = '
        SELECT id
        FROM
        '. $table .'
        ORDER BY
        id
        '. $order .'
        LIMIT 1
        ';

        $result = $this->exe($sql);

        $row = $result->fetch_assoc();

        return $row['id'];
    }


    public function get_first_id($table){

        return $this->get_last_id($table, order:true);
    }


    public function duplicate($table, $id, $idField='id') : int {

        // get entry
        $sql = '
        SELECT * FROM
        '. $table .'
        WHERE
        '. $idField .' = ?
        LIMIT 1
        ';

        $result = $this->exe($sql, $id);

        $row = $result->fetch_assoc();


        // set fields params
        $values = array();
        $fieldInfo = array();
        $q = array();

        // populate fields params
        foreach($result->fetch_fields() as $e){

            if($e->name == 'id'){
                continue;
            }

            $values[] = &$row[$e->name];
            $fieldInfo[] = $e->name;
            $q[] = '?';
        }

        // insert
        $sql = '
        INSERT INTO
        '. $table .'
        (`'. implode('`,`', $fieldInfo) .'`)
        VALUES('. implode(',', $q) .');
        ';

        $this->exe($sql, $values);


        // return last id
        return $this->get_last_id($table);
    }
    //planed to be deprecated use beginTransaction() instead
    public function start_transaction(?string $name){
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

      $this->doctrinedb->beginTransaction();
    }
    //planed to be deprecated use commit() instead
    public function commit_transaction(?string $name){
        
        $this->doctrinedb->commit();
    }
    //planed to be deprecated use rollback() instead
    public function rollback_transaction(?string $name){
        
        $this->doctrinedb->rollback();

    }

    public function beginTransaction(){
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
      $this->doctrinedb->beginTransaction();
    }  

    public function commit(){
        $this->doctrinedb->commit();
    }

    public function rollBack(){
        $this->doctrinedb->rollBack();
    }

    public static function print_in($values){

        return implode(',', array_fill(0, count($values), '?'));
    }


    public static function get_ref($array){

        $return = array();

        foreach($array as &$e){

            $return[] = &$e;
        }

        return $return;
    }

}