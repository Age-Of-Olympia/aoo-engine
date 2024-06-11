<?php

class Db{

    public function __construct(){

        $this->link = $this->db = db();
    }

    public function __destruct(){

        if(is_resource($this->db)){
            $this->db->close();
        }
    }


    public function exe($sql, $array=array()){

        $params = '';

        $stmt = $this->db->prepare($sql);

        if(!$stmt){

            echo $sql;
            exit('error stmt: check $sql');
        }


        // str to array
        if(!is_array($array)){

            $values = array(&$array);
        }
        else{

            $values = $array;
        }

        // if values
        if(count($values)){

            // params type
            foreach($values as $e){

                if(is_numeric($e)){
                    $params .= 'i';
                }
                else{
                    $params .= 's';
                }
            }


            // add params BEFORE $values
            array_unshift($values, $params);

            // execute $stmt->bind_param($params, $values...)
            call_user_func_array(array($stmt, 'bind_param'), $values);
        }

        $stmt->execute();

        $res = $stmt->get_result();

        if($res){

            return $res;
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

    public function insert($table, $values){

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

}
