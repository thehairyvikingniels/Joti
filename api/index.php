<?php
header('Content-Type: application/json');

require("../dblogin.php");


if (isset($_GET['token'])){
  if (checkToken($_GET['token'])) {
    $sql = "SELECT id,priv FROM Gebruikers WHERE api = '".$_GET['token']."'";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
      while($row = mysqli_fetch_assoc($result)) {
        $priv = $row['priv'];
        $id = $row['id'];
      }
      $output["status"]["code"] = 200;
    } else {
      $output["status"]["code"] = 400;
      $output["status"]["info"] = "Token expired/deleted";
      goto end;
    }
  } else {
    $output["status"]["code"] = 400;
    $output["status"]["info"] = "Token impossible";
    goto end;
  }
}




if (isset($_GET['users'])) {
  if ($_GET['users'] == "me"){ $query =  " AND api = '".$_GET['token']."'";}
  elseif (is_numeric($_GET['users'])) { $query =  " AND id = '".$_GET['users']."'";} else {$query = null;}
  $sql = "SELECT * FROM Gebruikers WHERE priv <= '".$priv."'$query";
  $result = mysqli_query($conn, $sql);
  if (mysqli_num_rows($result) > 0) {
    $a = 0;
    while($row = mysqli_fetch_assoc($result)) {
      $output["data"]["userdata"][$a]["id"] = $row["id"];
      $output["data"]["userdata"][$a]["username"] = $row["gebruikersnaam"];
      $output["data"]["userdata"][$a]["firstname"] = $row["voornaam"];
      $output["data"]["userdata"][$a]["lastname"] = $row["achternaam"];
      $output["data"]["userdata"][$a]["email"] = $row["email"];
      $output["data"]["userdata"][$a]["priv"] = $row["priv"];
      $a++;
    }
  }
}




if (isset($_GET['groups'])) {
  if (is_numeric($_GET['groups'])) { $query =  " WHERE id = '".$_GET['groups']."'";} 
  elseif ($_GET['groups'] == "me") { }
  else {$query = null;}
  $sql = "SELECT * FROM Groepen".$query;
  $result = mysqli_query($conn, $sql);
  if (mysqli_num_rows($result) > 0) {
    $a = 0;
    while($row = mysqli_fetch_assoc($result)) {
      $output["data"]["groups"][$a]["id"] = $row["id"];
      $output["data"]["groups"][$a]["name"] = $row["naam"];
      $output["data"]["groups"][$a]["teamname"] = $row["gebruikersnaam"];
      $output["data"]["groups"][$a]["street"] = $row["straat"];
      $output["data"]["groups"][$a]["number"] = $row["huisnummer"];
      $output["data"]["groups"][$a]["postalcode"] = $row["postcode"];
      $output["data"]["groups"][$a]["city"] = $row["plaats"];
      $output["data"]["groups"][$a]["lat"] = $row["lat"];
      $output["data"]["groups"][$a]["lon"] = $row["lon"];
      $output["data"]["groups"][$a]["url"] = $row["url"];
      $output["data"]["groups"][$a]["cluster"] = $row["deelgebied"];
      $a++;
    }
  }
}


if (isset($_GET['vossen'])) {
  $vossen_all = array("alpha","bravo","charlie","delta","echo","foxtrot");
  if (!empty($_GET['vossen'])){
    $vossen = explode(",",$_GET['vossen']);
    foreach($vossen as $vos) {
      if (in_array($vos,$vossen_all)){
      } else {
        $output["status"]["code"] = 400;
        $output["status"]["info"] = "Unknown Vos, grrrrr";
        goto end;
      }
    }
  } else {
    $vossen = $vossen_all;
  }
  if ($priv >= 1){
    $sql = "SELECT * FROM Voslog ORDER BY datumtijd asc";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
      $a = 0;
      while($row = mysqli_fetch_assoc($result)) {
        foreach($vossen as $vos){
          $sql_2 = "SELECT MAX(datumtijd) as datumtijd FROM Voslog orig WHERE $vos <> (SELECT $vos FROM Voslog WHERE id = orig.id - 1);";
          $result_2 = mysqli_query($conn, $sql_2);
          if (mysqli_num_rows($result_2) > 0) {
            while($row_2 = mysqli_fetch_assoc($result_2)) {
              $output["data"]["vossen"][$vos]["status_datetime"] = $row_2['datumtijd'];
            }
          }
          $output["data"]["vossen"][$vos]["mins"] = round(abs(strtotime(date('Y-m-d H:i:s')) - strtotime($output["data"]["vossen"][$vos]["status_datetime"])) / 60,0);
          $output["data"]["vossen"][$vos]["status"] = $row[$vos];         
          
          $output["data"]["vossen"][$vos]["location"]["lat"] = $row[$vos];
          $output["data"]["vossen"][$vos]["location"]["lon"] = $row[$vos];
          $output["data"]["vossen"][$vos]["location"]["datetime"] = "xx.xx.xxxx xx:xx:xx";
        }
        
        

        $a++;
      }
    }
  } else {
    $output["status"]["code"] = 401;
    $output["status"]["info"] = "Access Denied";
    goto end;
  }
}
  
  
if (isset($_POST['hunt'])){
  $hunt = json_decode($_POST['hunt'],true);
  if (isset($hunt['sector']) && isset($hunt['lat']) && isset($hunt['lon']) && isset($hunt['code']) && isset($hunt['datetime'])){
    if (preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $hunt['datetime'])) {
      // true
    } else {
      $output["status"]["code"] = 400;
      $output["status"]["info"] = "Invalid datetime. Use '2019-10-15 23:00'";
      goto end;
    }
    $sql = "INSERT INTO Voslocaties (deelgebied, coordinaat_x, coordinaat_y, type, code, ingeleverd, ingestuurd_op)
    VALUES ('".ucfirst($hunt['sector'])."', '".$hunt['lat']."', '".$hunt['lon']."','Hunt','".$hunt['code']."',0,'".$hunt['datetime']."')";

    if (mysqli_query($conn, $sql)) {
      $output["status"]["code"] = 200;
      $output["status"]["info"] = "New record created successfully";
    } else {
      $output["status"]["code"] = 500;
      $output["status"]["info"] = "Error: " . $sql . "<br>" . mysqli_error($conn);
      goto end;
    }
  } else {
    $output["status"]["code"] = 400;
    $output["status"]["info"] = "Bad request";
    goto end;
  }
  
  
}
  
  
  
  
end:
if (!empty($output)){echo json_encode($output,true);}


function checkToken($token){
  $api_key = explode(".",$token.".");
  $api_check = substr(sha1($api_key[0]."salt"),0,7);
  if ($api_key[1] == $api_check){
    return true;
    echo "true";
  } else {
    return false;
    echo "false";
  }
}


?>