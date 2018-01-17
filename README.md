# DbObj -- Do not download yet.
A simple script dependant of MySQLi for easy and quick MySQL connection and requests. This is for individual developers wanting to use a more light easy approach for a more tedious process. Still, it is not ready to download and use yet. Transporting the code from a __'standalone'__ purpose, has been challenging, since the original code I wrote was used with one specific customer, but after 10 years using this code I noticing missing it more than often with other projects.

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

# Very Important Note:
This object should be use only if you have access to a root none public directory not accessible publicly. Since the object makes it easy to connect with preconfigured credentials, *having it exposed can be a security debacle*.

If you do not have this kind of environment on a share hosting environment __you have to secure it in anyway__, _either with .htaccess or web.config_, only allowing the system to use it.
