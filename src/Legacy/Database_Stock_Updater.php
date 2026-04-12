<?php 
require('Database_Manager.php');
class Database_Stock_Updater extends Database_Manager{
    private $itemList;
    private $old_items;
    private $new_items;
    
    public function __construct(mysqli $con){
        parent::__construct($con);
        $this->itemList = [];
        $this->old_items = [];
        $this->new_items = [];
    }

    public function setItemList(Sql_Accounting_Export $file, array $UPLOADED_FILE){
        $validFile = $file->validateFile($UPLOADED_FILE, 'txt');
        if($validFile['valid'] === false){
            trigger_error($validFile['errormsg']);
            return false;
        }
        $file_data = trim(file_get_contents($UPLOADED_FILE['tmp_name']));
        
        $file->setItemList($file_data);

        $file->matrix_ItemList();

        $file->setPage_Index();

        $file->gatherRow_Data();

        $this->itemList = $file->getItemList();
    }

    public function doAllStuff(){
        $this->getAll_Stock_Items();
        $this->find_New_Items();
        $this->update_All_Old_Items_Stock();
        $this->insert_New_Items();
    }

    public function getAll_Stock_Items(){
        $sql = "SELECT id, item_code FROM stock_items;";
        $stmt = $this->exec_query($sql);
        if($stmt === false){
            return false;
        }

        while($row = mysqli_fetch_assoc($stmt)){
            $this->result[] = $row;
        }
        mysqli_free_result($stmt);
        
        return $this->result;
    }

    public function find_New_Items(){
        $old_items = [];
        $new_items = [];
        $result = [];
        foreach($this->result as $row){
            $result[strtolower(trim($row['item_code']))] = $row['id'];
        }
        unset($row);

        $temp = [];
        foreach($this->itemList as $k => $row){
            $itemCode = strtolower(trim($row['item_code']));
            if(array_key_exists($itemCode, $result)){
                $temp = $row;
                $temp['id'] = intval($result[$itemCode]);
                $old_items[] = $temp;
            } else {
                $new_items[] = $row;
            }
        }

        $this->old_items = $old_items;
        $this->new_items = $new_items;
    }

    public function update_All_Old_Items_Stock(){
        mysqli_autocommit($this->con, false);
        $sql = "UPDATE stock SET quantity = ? WHERE id = ? ;";
        $stmt = mysqli_prepare($this->con, $sql);
        if(!$stmt){
            trigger_error(mysqli_error($this->con));
            return false;
        }
        foreach($this->old_items as $row){
            mysqli_stmt_bind_param($stmt, 'ii', $row['quantity'], $row['id']);
            if(!mysqli_stmt_execute($stmt)){
                trigger_error(mysqli_error($this->con));
                mysqli_rollback($this->con);
                return false;
            }
        }
        mysqli_commit($this->con);
        mysqli_autocommit($this->con, true);
        return true;
    }

    public function insert_New_Items(){
        mysqli_autocommit($this->con, false);
        $sql = "INSERT INTO stock_items(item_code, description, uom, item_group) VALUES (?,?,?,?);";
        $stmt = mysqli_prepare($this->con, $sql);
        if(!$stmt){
            trigger_error(mysqli_error($this->con));
            return false;
        }

        $sql2 = "INSERT INTO stock(id, quantity) VALUES (?,?);";
        $stmt2 = mysqli_prepare($this->con, $sql2);
        if(!$stmt2){
            trigger_error(mysqli_error($this->con));
            return false;
        }
        foreach($this->new_items as $row){
            mysqli_stmt_bind_param($stmt, 'ssss', 
            $row['item_code'], $row['description'], $row['uom'], $row['item_group']);
            if(!mysqli_stmt_execute($stmt)){
                trigger_error(mysqli_error($this->con));
                mysqli_rollback($this->con);
                return false;
            }

            $last_id = mysqli_stmt_insert_id($stmt);
            mysqli_stmt_bind_param($stmt2, 'ii', $last_id , $row['quantity']);
            if(!mysqli_stmt_execute($stmt2)){
                trigger_error(mysqli_error($this->con));
                mysqli_rollback($this->con);
                return false;
            }
        }
        mysqli_commit($this->con);
        mysqli_autocommit($this->con, true);
        return true;
    }
}