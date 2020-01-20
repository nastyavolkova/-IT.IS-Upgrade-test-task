<?php

// соединение с БД
$servername = "127.0.0.1";
$database = "test";
$username = "root";
$password = "";

$conn = mysqli_connect($servername, $username, $password, $database);

// формирование первого отчета
if($_POST['type'] === 'report1' or $_POST['type'] === 'report2'){

    // получение из БД всех IP
    $query = "SELECT id_user, IP FROM users";
    $result_view = mysqli_query($conn, $query);
    $all_IP = mysqli_fetch_all($result_view);
    $rows = mysqli_num_rows($result_view);

    // определение количества массивов по 100 элементов
    $array_number = ceil($rows / 100);

    $all_country_name = array();

    for($i = 1; $i <= $array_number; $i++){
        
        $max = $i * 100;
        $index = $max - 100;
        $j = 0;

        // создание массивов и наполение их IP
        for($index; $index < $max; $index++){
            if(!array_key_exists($index, $all_IP)){
            break;
            }
            ${"IP_".$i}[$j] = $all_IP[$index][1];
            $j +=1;
        }
        
        $options = [
            'http' => [
                'method' => 'POST',
                'user_agent' => 'Batch-Example/1.0',    
                'header' => 'Content-Type: application/json',
                'content' => json_encode(${"IP_".$i})
            ]
        ];
        $response = file_get_contents('http://ip-api.com/batch?fields=country', false, stream_context_create($options));
            
        $array = (json_decode($response, true));

        $j = 0;
        $max = $i * 100;
        $index = $max - 100;

        // заполнение массива названиями стран
        for($index; $index < $max; $index++){

            if(!array_key_exists($index, $all_IP)){
            break;
            }
            if(empty($array[$j]) === true){

                $all_country_name[$all_IP[$index][0]] = "No geolocation";
            }
            else{
            
                $all_country_name[$all_IP[$index][0]] = $array[$j]['country'];
            }
            $j += 1;

        }

    }
}

// получение данных для первого отчета
if($_POST['type'] === 'report1'){

    $query = "SELECT id_user, id_prod_category, id_product, id_adding FROM views";
    $result_view = mysqli_query($conn, $query);
    $all_views = mysqli_fetch_all($result_view,MYSQLI_ASSOC);

    $query = "SELECT id_adding, id_purchase FROM addings";
    $result_view = mysqli_query($conn, $query);
    $all_addings = mysqli_fetch_all($result_view,MYSQLI_ASSOC);

    
    $activity = array();
    $array_purchase = array();

    foreach($all_views as $action){

        $id_user = $action['id_user'];
        $id_adding = $action['id_adding'];

        $action_count = 1;

        if($id_adding !== null){
            
            $action_count += 1;

            foreach($all_addings as $adding){

                if($adding['id_adding'] === $id_adding and $adding['id_purchase'] !== null and !in_array($adding['id_purchase'], $array_purchase)){
                    
                    $action_count += 1; 
                    $array_purchase[] = $adding['id_purchase'];
                break;
                }
            }
        }
        if(array_key_exists($all_country_name[$id_user], $activity)){
            $activity[$all_country_name[$id_user]] += $action_count;  
        }
        else{
            $activity[$all_country_name[$id_user]]= $action_count;
        }
    }
    $sum = array_sum($activity);
    arsort($activity);
    array_splice($activity, 10);

    $results = [];
    foreach ($activity as $key => $value) {
        array_push($results, [$key, $value / $sum]);
    }
    echo json_encode($results);

    exit();
}

// получение данных для второго отчета
if($_POST['type'] === 'report2'){

    $category = $_POST['category'];

    $query = "SELECT id_user FROM views WHERE id_prod_category = (SELECT id_prod_category FROM prod_categories WHERE category_name = '$category') and  id_product IS null";
    $result_view = mysqli_query($conn, $query);
    $all_views = mysqli_fetch_all($result_view,MYSQLI_ASSOC);

    $interest = array();

    foreach($all_views as $intresting){

        $id_user = $intresting['id_user'];
         
            $interest_count = 1;
        
        if(array_key_exists($all_country_name[$id_user], $interest)){
            $interest[$all_country_name[$id_user]] += $interest_count;  
        }
        else{
            $interest[$all_country_name[$id_user]]= $interest_count;
        }
        
    }
    $sum = array_sum($interest);
    arsort($interest);
    array_splice($interest, 10);

    $results = [];
    foreach ($interest as $key => $value) {
        array_push($results, [$key, $value / $sum]);
    }
    echo json_encode($results);

    exit();
}

// получение данных для третьего отчета
if($_POST['type'] === 'report3'){
    $date1 = $_POST['date1'];
    $time1 = $_POST['time1'];
    $date2 = $_POST['date2'];
    $time2 = $_POST['time2'];

    $datetime1 = $date1 . $time1;
    $datetime1 = date('Y-m-d H:i:s', strtotime($datetime1));

    $datetime2 = $date2 . $time2;
    $datetime2 = date('Y-m-d H:i:s', strtotime($datetime2));

    $query = "SELECT * FROM addings WHERE adding_time > '$datetime1' and adding_time < '$datetime2' and id_purchase is null";
    $result_addings = mysqli_query($conn, $query);
    $all_addings = mysqli_fetch_all($result_addings,MYSQLI_ASSOC);

    $unpaid_cart = count($all_addings);

    echo json_encode($unpaid_cart);
}
?>


   


