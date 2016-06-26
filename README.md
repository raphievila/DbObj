# DbObj
A simple script dependant of MySQLi for easy and quick MySQL connection and requests

# Setting up connection
The file will include a section to set a private static variable which includes user, schema, password and host. This is all that needs to be set up for the class to connect.

After these values are set, it is only required to call the class as follow:

    $db = new DbObj('schema_name');
    try { $qry = $db->query('Select col1,col2 FROM `table_name`'); }
    catch(Exception $e) {
        die($e->getMessage()):
    }
    $status = is_object($qry);
    $res = ($status) ? $db->numrows($qry) : 0;
    
    if($res > 0) {
        while($a = $db->object($qry)){
          echo $a->col1 . " ::: " . $a->col2;
        }
    }
    
    if($status){ $db->free($qry); }
    $db->disconnect();
    

#Multiple schema/users
You will also be able to set multiple users/schema as well. Which will allow you to connect to different schemas at the same time with different intiating
    
For example:

        $db1 = $db->DbObj('schema1');
        $db2 = $db->DbObj('schema2');
