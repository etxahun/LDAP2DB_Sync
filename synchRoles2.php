<?php
set_time_limit(30);
error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);
ini_set('display_errors',1);

// config
$ldapserver = 'yyy.yyy.yyy.yyy:8389';
$ldapuser = 'cn=Directory Manager';  
$ldappass = 'password';
$ldaptree = "OU=People,DC=telnex,DC=es";

// connect LDAP
$ldapconn = ldap_connect($ldapserver) or die("Could not connect to LDAP server.");

// connect MySQL
$dbconn =  mysql_connect('xxx.xxx.xxx.xxx:3306', 'root', 'password');
if (!$dbconn) {
    die('No pudo conectarse: ' . mysql_error());
}
//echo 'Conectado a la BBDD satisfactoriamente';
if (!mysql_select_db('predymol', $dbconn)) {
	echo 'No pudo seleccionar la base de datos';
	exit;
}

if($ldapconn) {
    // binding to ldap server
    $ldapbind = ldap_bind($ldapconn, $ldapuser, $ldappass) or die ("Error trying to bind: ".ldap_error($ldapconn));
    // verify binding
    if ($ldapbind) {
        //echo "<br />LDAP bind successful...<br />";
        
        $result = ldap_search($ldapconn,$ldaptree, "(employeetype=peg_*)") or die ("Error in search query: ".ldap_error($ldapconn));
        $data = ldap_get_entries($ldapconn, $result);
        
        // SHOW ALL DATA
        //echo '<h1>Dump all data</h1><pre>';
        //print_r($data);    
        //echo '</pre>';
        
        
        // iterate over array and print data for each entry
        //echo '<h1>Show me the users</h1>';
        for ($i=0; $i<$data["count"]; $i++) {
            //echo "dn is: ". $data[$i]["dn"] ."<br />";
			$ldapuser = $data[$i]["uid"][0];
            //echo "User: ". $ldapuser ."<br />";
            if(isset($data[$i]["employeetype"][0])) {
				$ldaprole = $data[$i]["employeetype"][0];
            } else {
				$ldaprole = "";
            }
            //echo "Role: ". $ldaprole ."<br />";
			$ldaprole = str_replace('peg_', '', $ldaprole);
			//echo "Role LDAP: ". $ldaprole ."<br />";
			
			
// para cada usuario, conectamos a la bbdd

			$sql = "SELECT Id, Name FROM `predymol`.`groups` where Id = (SELECT GroupId FROM `predymol`.`users_groups` WHERE UserId = (SELECT Id FROM `predymol`.`users` WHERE UID = '". $data[$i]["uid"][0] ."'))";
			$resultado = mysql_query($sql, $dbconn);

			if (!$resultado) {
				echo 'Error de BD, no se pudo consultar la base de datos\n';
				echo 'Error MySQL: ' . mysql_error();
				exit;
			}
			
			$dbrole = "";
			$dbroleid = "";
			while ($fila = mysql_fetch_assoc($resultado)) {
				$dbrole = $fila['Name'];
				$dbroleid = $fila['Id'];
				//echo "Role BBDD: ". $dbrole ."<br />";
			}

			mysql_free_result($resultado);

// fin consulta bbdd

			if (strcmp($ldaprole, $dbrole) == 0) {
				//echo "<b>Los roles coinciden</b><br />";
			} else {
				//echo "Los roles no coinciden<br />";
				// Aquí hay que hacer la update o la insert en la tabla users_groups
				if (strcmp($dbrole, "") == 0) {
					// si el usuario no tiene role asociado, hacer insert en users_groups
					$sql2 = "SELECT Id FROM `predymol`.`groups` where Name = '". $ldaprole ."'";
					$resultado2 = mysql_query($sql2, $dbconn);
					if (!$resultado2) {
						echo 'Error MySQL: ' . mysql_error();
						exit;
					}
					$dbroleid2 = "";
					while ($fila2 = mysql_fetch_assoc($resultado2)) {
						$dbroleid2 = $fila2['Id'];
					}
					$sql3 = "SELECT Id FROM `predymol`.`users` where UID = '". $ldapuser ."'";
					$resultado3 = mysql_query($sql3, $dbconn);
					if (!$resultado3) {
						echo 'Error MySQL: ' . mysql_error();
						exit;
					}
					$dbuserid = "";
					while ($fila3 = mysql_fetch_assoc($resultado3)) {
						$dbuserid = $fila3['Id'];
					}
					// comprobamos que existan el usuario y el rol en la base de datos
					if ((!strcmp($dbuserid, "") == 0) && (!strcmp($dbroleid2, "") == 0)) {
						$sql4 = "INSERT INTO `predymol`.`users_groups` (UserId, GroupId) VALUES (". $dbuserid .",". $dbroleid2 .")";
						$resultado4 = mysql_query($sql4, $dbconn);
						if (!$resultado4) {
							echo 'Error MySQL: ' . mysql_error();
							exit;
						}
					}
					//echo "Role BBDD insertado<br /><br />";
				} else {
					// si el usuario tiene role asociado pero no es el del ldap, hacer update en users_groups
					$sql2 = "SELECT Id FROM `predymol`.`groups` where Name = '". $ldaprole ."'";
					$resultado2 = mysql_query($sql2, $dbconn);
					if (!$resultado2) {
						echo 'Error MySQL: ' . mysql_error();
						exit;
					}
					$dbroleid2 = "";
					while ($fila2 = mysql_fetch_assoc($resultado2)) {
						$dbroleid2 = $fila2['Id'];
					}
					$sql3 = "SELECT Id FROM `predymol`.`users` where UID = '". $ldapuser ."'";
					$resultado3 = mysql_query($sql3, $dbconn);
					if (!$resultado3) {
						echo 'Error MySQL: ' . mysql_error();
						exit;
					}
					$dbuserid = "";
					while ($fila3 = mysql_fetch_assoc($resultado3)) {
						$dbuserid = $fila3['Id'];
					}
					// comprobamos que existan el usuario y el rol en la base de datos
					if ((!strcmp($dbuserid, "") == 0) && (!strcmp($dbroleid2, "") == 0)) {
						$sql4 = "UPDATE `predymol`.`users_groups` SET GroupId = ". $dbroleid2 ." WHERE UserId = ". $dbuserid;
						$resultado4 = mysql_query($sql4, $dbconn);
						if (!$resultado4) {
							echo 'Error MySQL: ' . mysql_error();
							exit;
						}
					}
					//echo "Role BBDD actualizado<br /><br />";
				}
			}
		}
        // print number of entries found
        //echo "Number of entries found: " . ldap_count_entries($ldapconn, $result);
    } else {
        echo "LDAP bind failed...";
    }
}

// connections clean up
ldap_close($ldapconn);
mysql_close($dbconn);
?>